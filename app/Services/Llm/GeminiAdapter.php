<?php

namespace App\Services\Llm;

use App\Models\LlmProvider;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAdapter implements LlmAdapterInterface
{
    public function send(LlmProvider $provider, string $systemPrompt, array $messages, array $mcpTools): LlmResponse
    {
        // Mock fallback for test keys
        if (str_starts_with($provider->api_key, 'sk-mock') || str_starts_with($provider->api_key, 'mock-') || empty($provider->api_key)) {
            $lastUser = collect($messages)->where('role', 'user')->last();
            $reply = "Hello from Gemini! I am your Company AI Agent. I received your request: '".($lastUser['content'] ?? '')."'. (Note: Google Gemini provider is running with a test key. Provide a live Gemini API key in the Admin Console to connect.)";

            return new LlmResponse(
                content: $reply,
                promptTokens: 40,
                completionTokens: 30
            );
        }

        // Format contents for Gemini
        $contents = [];

        foreach ($messages as $msg) {
            $role = $msg['role'];

            if ($role === 'system') {
                continue; // Handled in systemInstruction
            }

            if ($role === 'tool') {
                $contents[] = [
                    'role' => 'function',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $msg['tool_call_id'] ?? 'tool_result',
                                'response' => ['result' => $msg['content'] ?? ''],
                            ],
                        ],
                    ],
                ];
            } elseif ($role === 'assistant') {
                $parts = [];
                if (! empty($msg['content'])) {
                    $parts[] = ['text' => $msg['content']];
                }
                if (! empty($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        $rawArgs = $tc['function']['arguments'] ?? $tc['arguments'] ?? [];
                        $args = is_array($rawArgs) ? $rawArgs : json_decode($rawArgs, true) ?? [];
                        $parts[] = [
                            'functionCall' => [
                                'name' => $tc['function']['name'] ?? $tc['name'] ?? '',
                                'args' => (object) $args,
                            ],
                        ];
                    }
                }
                $contents[] = [
                    'role' => 'model',
                    'parts' => empty($parts) ? [['text' => 'Proceeding...']] : $parts,
                ];
            } else {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $msg['content'] ?? ''],
                    ],
                ];
            }
        }

        // Format MCP tools for Gemini functionDeclarations
        $functionDeclarations = [];
        foreach ($mcpTools as $t) {
            $fn = $t['function'];
            $functionDeclarations[] = [
                'name' => $fn['name'],
                'description' => $fn['description'] ?? 'MCP Tool',
                'parameters' => (object) ($fn['parameters'] ?? [
                    'type' => 'object',
                    'properties' => (object) [],
                ]),
            ];
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) $provider->temperature,
            ],
        ];

        if ($provider->max_tokens) {
            $payload['generationConfig']['maxOutputTokens'] = (int) $provider->max_tokens;
        }

        if (! empty($functionDeclarations)) {
            $payload['tools'] = [
                ['functionDeclarations' => $functionDeclarations],
            ];
        }

        $model = $provider->model ?: 'gemini-1.5-pro';
        $baseUrl = rtrim($provider->base_url ?: 'https://generativelanguage.googleapis.com', '/');
        $endpoint = "{$baseUrl}/v1beta/models/{$model}:generateContent?key={$provider->api_key}";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($endpoint, $payload);

            if (! $response->successful()) {
                return new LlmResponse(
                    errorMessage: "Gemini API Error ({$response->status()}): ".$response->body()
                );
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (! $candidate) {
                return new LlmResponse(errorMessage: 'Empty response returned by Gemini API.');
            }

            $parts = $candidate['content']['parts'] ?? [];
            $usage = $data['usageMetadata'] ?? [];

            $textContent = '';
            $toolCalls = [];

            foreach ($parts as $p) {
                if (! empty($p['text'])) {
                    $textContent .= $p['text'];
                }
                if (! empty($p['functionCall'])) {
                    $fc = $p['functionCall'];
                    $toolCalls[] = [
                        'id' => 'call_'.uniqid(),
                        'name' => $fc['name'] ?? '',
                        'arguments' => (array) ($fc['args'] ?? []),
                    ];
                }
            }

            return new LlmResponse(
                content: ! empty($textContent) ? $textContent : null,
                toolCalls: $toolCalls,
                promptTokens: (int) ($usage['promptTokenCount'] ?? 0),
                completionTokens: (int) ($usage['candidatesTokenCount'] ?? 0)
            );
        } catch (Exception $e) {
            Log::error('GeminiAdapter Exception: '.$e->getMessage());

            return new LlmResponse(errorMessage: $e->getMessage());
        }
    }
}
