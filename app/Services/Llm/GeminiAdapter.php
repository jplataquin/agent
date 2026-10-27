<?php

namespace App\Services\Llm;

use App\Models\LlmLog;
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

        // Format raw contents for Gemini
        $rawContents = [];

        foreach ($messages as $msg) {
            $role = $msg['role'];

            if ($role === 'system') {
                continue; // Handled in systemInstruction
            }

            if ($role === 'tool') {
                $toolName = $msg['tool_name'] ?? $msg['tool_call_id'] ?? 'tool_result';
                $rawContent = $msg['content'] ?? '';
                $decoded = is_string($rawContent) ? json_decode($rawContent, true) : $rawContent;

                if (is_array($decoded)) {
                    if (! empty($decoded) && array_keys($decoded) === range(0, count($decoded) - 1)) {
                        $responsePayload = ['result' => $decoded];
                    } else {
                        $responsePayload = empty($decoded) ? ['result' => 'ok'] : $decoded;
                    }
                } else {
                    $responsePayload = ['content' => (string) $rawContent];
                }

                $rawContents[] = [
                    'role' => 'user', // In Gemini REST API, functionResponse has role 'user'
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $toolName,
                                'response' => (object) $responsePayload,
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
                        $part = [
                            'functionCall' => [
                                'name' => $tc['function']['name'] ?? $tc['name'] ?? '',
                                'args' => (object) $args,
                            ],
                        ];

                        $thoughtSig = $tc['thought_signature']
                            ?? $tc['thoughtSignature']
                            ?? ($tc['function']['thought_signature'] ?? null)
                            ?? ($tc['function']['thoughtSignature'] ?? null)
                            ?? ($msg['thought_signature'] ?? null)
                            ?? ($msg['thoughtSignature'] ?? null);

                        if (! empty($thoughtSig) && $thoughtSig !== 'skip_thought_signature_validator') {
                            $part['thought_signature'] = $thoughtSig;
                        }

                        $parts[] = $part;
                    }
                }
                $rawContents[] = [
                    'role' => 'model',
                    'parts' => empty($parts) ? [['text' => 'Proceeding with tool execution...']] : $parts,
                ];
            } else {
                $rawContents[] = [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $msg['content'] ?? ''],
                    ],
                ];
            }
        }

        // Normalize turn alternation: Gemini strictly requires user -> model -> user -> model
        $normalizedContents = [];
        foreach ($rawContents as $entry) {
            // Remove empty text parts
            $filteredParts = array_values(array_filter($entry['parts'], function ($part) {
                if (isset($part['text']) && trim($part['text']) === '') {
                    return false;
                }

                return true;
            }));

            if (empty($filteredParts)) {
                continue;
            }

            $entry['parts'] = $filteredParts;

            $lastIdx = count($normalizedContents) - 1;
            if ($lastIdx >= 0 && $normalizedContents[$lastIdx]['role'] === $entry['role']) {
                $lastHasFuncResponse = false;
                foreach ($normalizedContents[$lastIdx]['parts'] as $p) {
                    if (isset($p['functionResponse'])) {
                        $lastHasFuncResponse = true;
                        break;
                    }
                }
                $incomingHasFuncResponse = false;
                foreach ($entry['parts'] as $p) {
                    if (isset($p['functionResponse'])) {
                        $incomingHasFuncResponse = true;
                        break;
                    }
                }

                // If one turn is a tool functionResponse and the other is user text, do not merge; insert model acknowledgment
                if ($lastHasFuncResponse !== $incomingHasFuncResponse) {
                    $normalizedContents[] = [
                        'role' => 'model',
                        'parts' => [['text' => 'Tool execution completed.']],
                    ];
                    $normalizedContents[] = $entry;
                } else {
                    $normalizedContents[$lastIdx]['parts'] = array_merge(
                        $normalizedContents[$lastIdx]['parts'],
                        $entry['parts']
                    );
                }
            } else {
                $normalizedContents[] = $entry;
            }
        }

        // Gemini requires the initial turn to be 'user'
        if (! empty($normalizedContents) && $normalizedContents[0]['role'] === 'model') {
            array_unshift($normalizedContents, [
                'role' => 'user',
                'parts' => [['text' => 'Hello']],
            ]);
        }

        if (empty($normalizedContents)) {
            $normalizedContents[] = [
                'role' => 'user',
                'parts' => [['text' => 'Hello']],
            ];
        }

        // Format MCP tools for Gemini functionDeclarations with uppercase Schema enum types
        $functionDeclarations = [];
        foreach ($mcpTools as $t) {
            $fn = $t['function'];
            $params = $fn['parameters'] ?? ['type' => 'object', 'properties' => []];
            $functionDeclarations[] = [
                'name' => $fn['name'],
                'description' => $fn['description'] ?? 'MCP Tool',
                'parameters' => $this->convertSchemaToGemini(is_array($params) ? $params : []),
            ];
        }

        $payload = [
            'contents' => $normalizedContents,
            'generationConfig' => [
                'temperature' => (float) $provider->temperature,
            ],
        ];

        if (! empty(trim($systemPrompt))) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        if ($provider->max_tokens) {
            $payload['generationConfig']['maxOutputTokens'] = (int) $provider->max_tokens;
        }

        if (! empty($functionDeclarations)) {
            $payload['tools'] = [
                ['functionDeclarations' => $functionDeclarations],
            ];
        }

        $model = $provider->model ?: 'gemini-1.5-flash';
        $model = preg_replace('/^(models\/|google\/)/', '', $model);
        $model = preg_replace('/:free$/', '', $model);
        if (str_contains(strtolower($model), 'gpt-')) {
            $model = 'gemini-1.5-flash';
        }

        $baseUrl = rtrim($provider->base_url ?: 'https://generativelanguage.googleapis.com', '/');
        if (str_contains($baseUrl, 'api.openai.com')) {
            $baseUrl = 'https://generativelanguage.googleapis.com';
        }
        $endpoint = "{$baseUrl}/v1beta/models/{$model}:generateContent?key={$provider->api_key}";

        $startTime = microtime(true);
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($endpoint, $payload);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $rawBody = $response->body();

            if (! $response->successful()) {
                $errBody = $response->body();
                $errJson = $response->json();
                $errDetail = $errJson['error']['message'] ?? $errBody;
                Log::error("Gemini API Error ({$statusCode}): {$errDetail}");

                LlmLog::record(
                    provider: $provider,
                    endpoint: $endpoint,
                    httpMethod: 'POST',
                    requestPayload: $payload,
                    statusCode: $statusCode,
                    responseBody: $rawBody,
                    latencyMs: $latencyMs,
                    isSuccess: false,
                    errorMessage: "Gemini API Error ({$statusCode}): {$errDetail}",
                );

                return new LlmResponse(
                    errorMessage: "Gemini API Error ({$statusCode}): {$errDetail}"
                );
            }

            $data = $response->json();
            $candidate = $data['candidates'][0] ?? null;

            if (! $candidate) {
                $blockReason = $data['promptFeedback']['blockReason'] ?? null;
                $msg = $blockReason
                    ? "Gemini blocked the request due to content safety policy ({$blockReason})."
                    : 'Gemini returned an empty response with no candidates.';

                LlmLog::record(
                    provider: $provider,
                    endpoint: $endpoint,
                    httpMethod: 'POST',
                    requestPayload: $payload,
                    statusCode: $statusCode,
                    responseBody: $rawBody,
                    latencyMs: $latencyMs,
                    isSuccess: false,
                    errorMessage: $msg,
                );

                return new LlmResponse(errorMessage: $msg);
            }

            $parts = $candidate['content']['parts'] ?? [];
            $usage = $data['usageMetadata'] ?? [];

            $textContent = '';
            $thoughtContent = '';
            $toolCalls = [];

            // Detect any candidate-level or content-level thought signature
            $candidateThoughtSig = $candidate['thought_signature']
                ?? $candidate['thoughtSignature']
                ?? ($candidate['content']['thought_signature'] ?? null)
                ?? ($candidate['content']['thoughtSignature'] ?? null);

            $lastThoughtSig = $candidateThoughtSig;
            foreach ($parts as $p) {
                if (! empty($p['thought_signature'])) {
                    $lastThoughtSig = $p['thought_signature'];
                } elseif (! empty($p['thoughtSignature'])) {
                    $lastThoughtSig = $p['thoughtSignature'];
                }
            }

            foreach ($parts as $p) {
                if (! empty($p['text'])) {
                    if (empty($p['thought'])) {
                        $textContent .= $p['text'];
                    } else {
                        $thoughtContent .= $p['text'];
                    }
                }
                if (! empty($p['functionCall'])) {
                    $fc = $p['functionCall'];
                    $sig = $p['thought_signature']
                        ?? $p['thoughtSignature']
                        ?? ($fc['thought_signature'] ?? null)
                        ?? ($fc['thoughtSignature'] ?? null)
                        ?? $lastThoughtSig;

                    $callData = [
                        'id' => 'call_'.uniqid(),
                        'name' => $fc['name'] ?? '',
                        'arguments' => (array) ($fc['args'] ?? []),
                    ];

                    if (! empty($sig) && $sig !== 'skip_thought_signature_validator') {
                        $callData['thought_signature'] = $sig;
                    }

                    $toolCalls[] = $callData;
                }
            }

            if (empty($textContent) && empty($toolCalls)) {
                if (! empty($thoughtContent)) {
                    $textContent = $thoughtContent;
                } else {
                    $finishReason = $candidate['finishReason'] ?? 'STOP';
                    if ($finishReason !== 'STOP') {
                        $textContent = "*(Gemini completed with status: {$finishReason})*";
                    }
                }
            }

            $promptTokens = (int) ($usage['promptTokenCount'] ?? 0);
            $completionTokens = (int) ($usage['candidatesTokenCount'] ?? 0);

            LlmLog::record(
                provider: $provider,
                endpoint: $endpoint,
                httpMethod: 'POST',
                requestPayload: $payload,
                statusCode: $statusCode,
                responseBody: $rawBody,
                latencyMs: $latencyMs,
                isSuccess: true,
                errorMessage: null,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
            );

            return new LlmResponse(
                content: ! empty($textContent) ? $textContent : null,
                toolCalls: $toolCalls,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens
            );
        } catch (\Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            Log::error('GeminiAdapter Exception: '.$e->getMessage());

            LlmLog::record(
                provider: $provider,
                endpoint: $endpoint,
                httpMethod: 'POST',
                requestPayload: $payload,
                statusCode: 0,
                responseBody: null,
                latencyMs: $latencyMs,
                isSuccess: false,
                errorMessage: 'Connection to Gemini API failed: '.$e->getMessage(),
            );

            return new LlmResponse(errorMessage: 'Connection to Gemini API failed: '.$e->getMessage());
        }
    }

    /**
     * Convert OpenAPI/JSON Schema parameters to Google Gemini Schema format.
     */
    protected function convertSchemaToGemini(array $schema): array
    {
        $type = strtoupper((string) ($schema['type'] ?? 'OBJECT'));
        $result = [
            'type' => $type,
        ];

        if (! empty($schema['description'])) {
            $result['description'] = (string) $schema['description'];
        }

        if ($type === 'OBJECT') {
            $properties = [];
            if (! empty($schema['properties']) && is_array($schema['properties'])) {
                foreach ($schema['properties'] as $propName => $propDef) {
                    if (is_array($propDef)) {
                        $properties[$propName] = $this->convertSchemaToGemini($propDef);
                    }
                }
            }
            $result['properties'] = (object) $properties;

            if (! empty($schema['required']) && is_array($schema['required'])) {
                $result['required'] = array_values($schema['required']);
            }
        } elseif ($type === 'ARRAY') {
            if (! empty($schema['items']) && is_array($schema['items'])) {
                $result['items'] = $this->convertSchemaToGemini($schema['items']);
            }
        }

        return $result;
    }
}
