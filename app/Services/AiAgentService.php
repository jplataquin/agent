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

        $promptParts[] = "\nWhen relevant, use the available tools connected via MCP to look up live internal data before responding. Never fabricate data from internal systems if a tool is available.";

        return implode("\n\n", $promptParts);
    }

    /**
     * Assemble message history for the LLM.
     */
    public function buildMessagesPayload(ChatSession $session): array
    {
        $messages = [];
        $history = $session->messages()->orderBy('id', 'asc')->get();

        foreach ($history as $msg) {
            $entry = ['role' => $msg->role];

            if ($msg->role === 'tool') {
                $entry['content'] = $msg->content ?? '';
                $entry['tool_call_id'] = $msg->tool_call_id ?? 'call_1';
            } elseif ($msg->role === 'assistant') {
                $entry['content'] = $msg->content;
                if (! empty($msg->tool_calls)) {
                    $entry['tool_calls'] = $msg->tool_calls;
                }
            } else {
                $entry['content'] = $msg->content ?? '';
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

        $maxIterations = 5; // prevent infinite tool loops
        $currentIteration = 0;
        $totalPromptTokens = 0;
        $totalCompletionTokens = 0;

        $systemPrompt = $this->buildSystemPrompt();
        $rawTools = $this->mcpService->getAllActiveTools();
        $adapter = $this->adapterFactory->make($provider);

        while ($currentIteration < $maxIterations) {
            $currentIteration++;
            $messages = $this->buildMessagesPayload($session);

            try {
                $llmResponse = $adapter->send($provider, $systemPrompt, $messages, $rawTools);

                if ($llmResponse->isError()) {
                    return [
                        'error' => $llmResponse->errorMessage,
                        'content' => $llmResponse->errorMessage,
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

                        if ($onChunk) {
                            $onChunk("\n*[Querying MCP Tool: {$funcName}...]*\n\n");
                        }

                        $toolExecution = $this->mcpService->executeTool($funcName, $args);

                        // Save tool response message
                        ChatMessage::create([
                            'chat_session_id' => $session->id,
                            'role' => ChatMessage::ROLE_TOOL,
                            'content' => $toolExecution['output'],
                            'tool_call_id' => $toolCall['id'] ?? 'call_'.uniqid(),
                        ]);
                    }

                    // Loop again so LLM synthesizes tool execution results into final answer
                    continue;
                }

                // Final answer produced
                $content = $llmResponse->content ?? 'Response completed.';

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
            } catch (Exception $e) {
                Log::error('AiAgentService error: '.$e->getMessage());

                return [
                    'error' => $e->getMessage(),
                    'content' => 'Error generating response: '.$e->getMessage(),
                ];
            }
        }

        return ['content' => 'Completed'];
    }

    /**
     * Record token metrics and estimated costs.
     */
    protected function recordUsage(ChatSession $session, LlmProvider $provider, int $promptTokens, int $completionTokens): void
    {
        $totalTokens = $promptTokens + $completionTokens;

        // Model tiered pricing calculation (per 1k tokens)
        $costPer1k = match (true) {
            str_contains($provider->model, 'gpt-4o-mini') => 0.0003,
            str_contains($provider->model, 'gpt-4o') => 0.005,
            str_contains($provider->model, 'claude-3-5') => 0.006,
            str_contains($provider->model, 'gemini-1.5-flash') => 0.0002,
            str_contains($provider->model, 'gemini-1.5-pro') => 0.0035,
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
}
