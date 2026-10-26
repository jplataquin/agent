<?php

namespace App\Services\Llm;

use App\Models\LlmProvider;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicAdapter implements LlmAdapterInterface
{
    public function send(LlmProvider $provider, string $systemPrompt, array $messages, array $mcpTools): LlmResponse
    {
        // Mock fallback for test keys
        if (str_starts_with($provider->api_key, 'sk-mock') || str_starts_with($provider->api_key, 'mock-') || empty($provider->api_key)) {
            $lastUser = collect($messages)->where('role', 'user')->last();
            $reply = "Hello from Claude! I am your Company AI Agent. I received your request: '".($lastUser['content'] ?? '')."'. (Note: Anthropic Claude provider is running with a test key. Provide a live key in the Admin Console to connect.)";

            return new LlmResponse(
                content: $reply,
                promptTokens: 45,
                completionTokens: 35
            );
        }

        // Format messages for Anthropic (alternating user / assistant blocks)
        $formattedMessages = [];

        foreach ($messages as $msg) {
            $role = $msg['role'];

            if ($role === 'system') {
                continue; // Handled via top-level 'system' parameter
            }

            if ($role === 'tool') {
                // In Anthropic, tool execution results are passed as user content blocks
                $formattedMessages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $msg['tool_call_id'] ?? 'call_1',
                            'content' => $msg['content'] ?? '',
                        ],
                    ],
                ];
            } elseif ($role === 'assistant') {
                $blocks = [];
                if (! empty($msg['content'])) {
                    $blocks[] = ['type' => 'text', 'text' => $msg['content']];
                }
                if (! empty($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        $rawArgs = $tc['function']['arguments'] ?? $tc['arguments'] ?? [];
                        $args = is_array($rawArgs) ? $rawArgs : json_decode($rawArgs, true) ?? [];
                        $blocks[] = [
                            'type' => 'tool_use',
                            'id' => $tc['id'] ?? 'call_'.uniqid(),
                            'name' => $tc['function']['name'] ?? $tc['name'] ?? '',
                            'input' => (object) $args,
                        ];
                    }
                }
                $formattedMessages[] = [
                    'role' => 'assistant',
                    'content' => empty($blocks) ? 'Proceeding...' : $blocks,
                ];
            } else {
                // Standard user message
                $formattedMessages[] = [
                    'role' => 'user',
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        // Format MCP tools for Anthropic (name, description, input_schema)
        $tools = [];
        foreach ($mcpTools as $t) {
            $fn = $t['function'];
            $tools[] = [
                'name' => $fn['name'],
                'description' => $fn['description'] ?? 'MCP Tool',
                'input_schema' => (object) ($fn['parameters'] ?? [
                    'type' => 'object',
                    'properties' => (object) [],
                ]),
            ];
        }

        $payload = [
            'model' => $provider->model,
            'max_tokens' => (int) ($provider->max_tokens ?: 2048),
            'system' => $systemPrompt,
            'messages' => $formattedMessages,
            'temperature' => (float) $provider->temperature,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $baseUrl = rtrim($provider->base_url ?: 'https://api.anthropic.com', '/');
        $endpoint = $baseUrl.'/v1/messages';

        try {
            $response = Http::withHeaders([
                'x-api-key' => $provider->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($endpoint, $payload);

            if (! $response->successful()) {
                return new LlmResponse(
                    errorMessage: "Anthropic API Error ({$response->status()}): ".$response->body()
                );
            }

            $data = $response->json();
            $contentBlocks = $data['content'] ?? [];
            $usage = $data['usage'] ?? [];

            $textContent = '';
            $toolCalls = [];

            foreach ($contentBlocks as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $textContent .= $block['text'] ?? '';
                } elseif (($block['type'] ?? '') === 'tool_use') {
                    $toolCalls[] = [
                        'id' => $block['id'] ?? 'call_'.uniqid(),
                        'name' => $block['name'] ?? '',
                        'arguments' => (array) ($block['input'] ?? []),
                    ];
                }
            }

            return new LlmResponse(
                content: ! empty($textContent) ? $textContent : null,
                toolCalls: $toolCalls,
                promptTokens: (int) ($usage['input_tokens'] ?? 0),
                completionTokens: (int) ($usage['output_tokens'] ?? 0)
            );
        } catch (Exception $e) {
            Log::error('AnthropicAdapter Exception: '.$e->getMessage());

            return new LlmResponse(errorMessage: $e->getMessage());
        }
    }
}
