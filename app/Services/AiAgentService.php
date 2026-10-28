<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\LlmProvider;
use App\Models\MasterPrompt;
use App\Models\Skill;
use App\Models\TokenMetric;
use App\Services\Llm\LlmAdapterFactory;
use Exception;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    public function __construct(
        protected McpService $mcpService,
        protected LlmAdapterFactory $adapterFactory
    ) {}

    /**
     * Assemble full system prompt from Master Prompt and Active Skills.
     */
    public function buildSystemPrompt(): string
    {
        $promptParts = [];

        // 1. Master Prompt
        $masterPrompt = MasterPrompt::where('is_active', true)->first();
        if ($masterPrompt) {
            $promptParts[] = $masterPrompt->content;
        } else {
            $promptParts[] = 'You are the Company AI Agent. Assist users with professional, helpful, and accurate answers.';
        }

        // 2. Active Skills
        $skills = Skill::where('is_active', true)->get();
        if ($skills->isNotEmpty()) {
            $promptParts[] = "\n## Active Specialized Skills & Guidelines:";
            foreach ($skills as $skill) {
                $promptParts[] = "### Skill: {$skill->name}\n{$skill->instructions}\n";
            }
        }

        $promptParts[] = "\nWhen relevant, use the available tools connected via MCP to look up live internal data before responding. Never fabricate data from internal systems if a tool is available.

Important Tool Response Guidelines:
- Tool execution results and tool JSON payloads are strictly for your internal reference.
- NEVER output raw JSON, JSON schemas, or raw tool response data in your chat response to the user.
- Always synthesize the tool results into a friendly, clear, natural, and helpful answer in plain text or Markdown.";

        return implode("\n\n", $promptParts);
    }

    /**
     * Assemble message history for the LLM.
     */
    public function buildMessagesPayload(ChatSession $session): array
    {
        $messages = [];
        $history = $session->messages()->orderBy('id', 'asc')->get();

        $lastAssistantToolCalls = [];

        foreach ($history as $msg) {
            $entry = ['role' => $msg->role];

            if ($msg->role === 'tool') {
                $entry['content'] = $msg->content ?? '';
                $entry['tool_call_id'] = $msg->tool_call_id ?? '';

                // Resolve tool_name by finding matching tool_call in preceding assistant message
                $toolName = null;
                foreach ($lastAssistantToolCalls as $tc) {
                    if (($tc['id'] ?? '') === $msg->tool_call_id) {
                        $toolName = $tc['name'] ?? ($tc['function']['name'] ?? null);
                        break;
                    }
                }
                if (! $toolName && ! empty($lastAssistantToolCalls)) {
                    $toolName = $lastAssistantToolCalls[0]['name'] ?? ($lastAssistantToolCalls[0]['function']['name'] ?? null);
                }
                if (! $toolName) {
                    foreach ($history as $prevMsg) {
                        if ($prevMsg->role === 'assistant' && ! empty($prevMsg->tool_calls)) {
                            foreach ($prevMsg->tool_calls as $tc) {
                                if (($tc['id'] ?? '') === $msg->tool_call_id) {
                                    $toolName = $tc['name'] ?? ($tc['function']['name'] ?? null);
                                    break 2;
                                }
                            }
                        }
                    }
                }
                $entry['tool_name'] = $toolName ?: 'mcp_tool';
            } elseif ($msg->role === 'assistant') {
                $entry['content'] = $msg->content;
                if (! empty($msg->tool_calls)) {
                    $entry['tool_calls'] = $msg->tool_calls;
                    $lastAssistantToolCalls = $msg->tool_calls;
                } else {
                    $lastAssistantToolCalls = [];
                }
            } else {
                $entry['content'] = $msg->content ?? '';
                $lastAssistantToolCalls = [];
            }

            $messages[] = $entry;
        }

        return $messages;
    }

    /**
     * Send chat request to configured LLM Provider using the appropriate provider adapter.
     * Supports tool execution loop.
     */
    public function generateResponse(ChatSession $session, ?callable $onChunk = null): array
    {
        $provider = LlmProvider::getDefault();

        if (! $provider) {
            return [
                'error' => 'No active LLM Provider configured. Please contact the administrator.',
                'content' => 'Error: No active LLM provider found. Please configure an LLM provider in the Admin Console.',
            ];
        }

        // Maximum tool call limit configured by admin; if not defined (null or <= 0), there is no limit.
        $maxIterations = ($provider->max_tool_iterations !== null && $provider->max_tool_iterations > 0)
            ? (int) $provider->max_tool_iterations
            : null;

        $currentIteration = 0;
        $totalPromptTokens = 0;
        $totalCompletionTokens = 0;

        $systemPrompt = $this->buildSystemPrompt();
        $rawTools = $this->mcpService->getAllActiveTools();
        $adapter = $this->adapterFactory->make($provider);

        while ($maxIterations === null || $currentIteration < $maxIterations) {
            $currentIteration++;
            $messages = $this->buildMessagesPayload($session);

            try {
                $llmResponse = $adapter->send($provider, $systemPrompt, $messages, $rawTools);

                if ($llmResponse->isError()) {
                    $errText = "⚠️ **AI Provider Error:**\n\n{$llmResponse->errorMessage}";
                    if ($onChunk) {
                        $onChunk($errText);
                    }

                    ChatMessage::create([
                        'chat_session_id' => $session->id,
                        'role' => ChatMessage::ROLE_ASSISTANT,
                        'content' => $errText,
                    ]);

                    return [
                        'error' => $llmResponse->errorMessage,
                        'content' => $errText,
                    ];
                }

                $totalPromptTokens += $llmResponse->promptTokens;
                $totalCompletionTokens += $llmResponse->completionTokens;

                // Handle tool calls requested by the model
                if ($llmResponse->hasToolCalls()) {
                    // Record the assistant's tool call request in chat history
                    ChatMessage::create([
                        'chat_session_id' => $session->id,
                        'role' => ChatMessage::ROLE_ASSISTANT,
                        'content' => $llmResponse->content,
                        'tool_calls' => $llmResponse->toolCalls,
                    ]);

                    foreach ($llmResponse->toolCalls as $toolCall) {
                        $funcName = $toolCall['name'] ?? '';
                        $args = $toolCall['arguments'] ?? [];

                        $toolExecution = $this->mcpService->executeTool($funcName, $args);

                        // Save tool response message
                        ChatMessage::create([
                            'chat_session_id' => $session->id,
                            'role' => ChatMessage::ROLE_TOOL,
                            'content' => $toolExecution['output'],
                            'tool_call_id' => $toolCall['id'] ?? 'call_'.uniqid(),
                        ]);
                    }

                    // If max limit is defined and reached, break loop to force final synthesis
                    if ($maxIterations !== null && $currentIteration >= $maxIterations) {
                        break;
                    }

                    // Loop again so LLM synthesizes tool execution results into final answer
                    continue;
                }

                // Final answer produced naturally by the model
                $content = $llmResponse->content ?? 'Response completed.';
                $content = $this->cleanAssistantReply($content);

                if ($onChunk) {
                    $onChunk($content);
                }

                $savedMsg = ChatMessage::create([
                    'chat_session_id' => $session->id,
                    'role' => ChatMessage::ROLE_ASSISTANT,
                    'content' => $content,
                ]);

                $this->recordUsage($session, $provider, $totalPromptTokens, $totalCompletionTokens);

                return [
                    'content' => $content,
                    'message_id' => $savedMsg->id,
                ];
            } catch (\Throwable $e) {
                Log::error('AiAgentService error: '.$e->getMessage());

                return [
                    'error' => $e->getMessage(),
                    'content' => 'Error generating response: '.$e->getMessage(),
                ];
            }
        }

        // Tool execution limit reached: force final synthesis turn with tools disabled
        $messages = $this->buildMessagesPayload($session);
        $synthesisPrompt = $systemPrompt."\n\nNotice: Maximum tool execution limit reached. Provide a comprehensive final answer to the user synthesizing all information gathered from the tools above. Do not attempt to call any more tools.";

        try {
            $finalResponse = $adapter->send($provider, $synthesisPrompt, $messages, []);

            if (! $finalResponse->isError() && ! empty($finalResponse->content)) {
                $content = $finalResponse->content;
            } else {
                $lastToolMsg = $session->messages()->where('role', ChatMessage::ROLE_TOOL)->latest()->first();
                $content = $lastToolMsg && ! empty($lastToolMsg->content)
                    ? "Here is the information retrieved from available systems:\n\n".$lastToolMsg->content
                    : 'The requested tool operations have finished.';
            }

            $totalPromptTokens += $finalResponse->promptTokens;
            $totalCompletionTokens += $finalResponse->completionTokens;

            if ($onChunk) {
                $onChunk($content);
            }

            $savedMsg = ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $content,
            ]);

            $this->recordUsage($session, $provider, $totalPromptTokens, $totalCompletionTokens);

            return [
                'content' => $content,
                'message_id' => $savedMsg->id,
            ];
        } catch (\Throwable $e) {
            Log::error('AiAgentService final synthesis error: '.$e->getMessage());

            $lastToolMsg = $session->messages()->where('role', ChatMessage::ROLE_TOOL)->latest()->first();
            $fallbackContent = $lastToolMsg && ! empty($lastToolMsg->content)
                ? "Here is the information retrieved from available systems:\n\n".$lastToolMsg->content
                : 'The tool operations completed, but an error occurred synthesizing the summary: '.$e->getMessage();

            $savedMsg = ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $fallbackContent,
            ]);

            return [
                'content' => $fallbackContent,
                'message_id' => $savedMsg->id,
            ];
        }
    }

    /**
     * Record token metrics and estimated costs.
     */
    protected function recordUsage(ChatSession $session, LlmProvider $provider, int $promptTokens, int $completionTokens): void
    {
        $totalTokens = $promptTokens + $completionTokens;

        $modelName = strtolower((string) ($provider->model ?? ''));

        // Model tiered pricing calculation (per 1k tokens)
        $costPer1k = match (true) {
            str_contains($modelName, 'gpt-4o-mini') => 0.0003,
            str_contains($modelName, 'gpt-4o') => 0.005,
            str_contains($modelName, 'claude-3-5') => 0.006,
            str_contains($modelName, 'gemini-1.5-flash') => 0.0002,
            str_contains($modelName, 'gemini-1.5-pro') => 0.0035,
            default => 0.002,
        };

        $estimatedCost = ($totalTokens / 1000) * $costPer1k;

        TokenMetric::create([
            'user_id' => $session->user_id,
            'chat_session_id' => $session->id,
            'provider' => $provider->name,
            'model' => $provider->model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
        ]);
    }

    /**
     * Ensure the final assistant response does not leak raw tool JSON payloads.
     */
    protected function cleanAssistantReply(string $content): string
    {
        $trimmed = trim($content);

        // Check for markdown code blocks containing json with tool-like structures
        if (preg_match('/^```(?:json)?\s*(\{[\s\S]*\}|\[[\s\S]*\])\s*```$/', $trimmed, $matches)) {
            $parsed = json_decode($matches[1], true);
            if (is_array($parsed)) {
                return $this->formatJsonPayloadToText($parsed);
            }
        }

        // Check if the entire reply is a raw JSON string
        if ((str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
            (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))) {
            $parsed = json_decode($trimmed, true);
            if (is_array($parsed)) {
                return $this->formatJsonPayloadToText($parsed);
            }
        }

        return $content;
    }

    protected function formatJsonPayloadToText(array $data): string
    {
        if (isset($data['output']) && is_string($data['output'])) {
            return $data['output'];
        }
        if (isset($data['result']) && is_string($data['result'])) {
            return $data['result'];
        }
        if (isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }

        $lines = [];
        foreach ($data as $key => $val) {
            $label = ucwords(str_replace('_', ' ', (string) $key));
            if (is_array($val)) {
                $lines[] = "**{$label}**: ".json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                $lines[] = "**{$label}**: {$val}";
            }
        }

        return implode("\n\n", $lines);
    }
}
