<?php

namespace App\Services\Llm;

use App\Models\LlmLog;
use App\Models\LlmProvider;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiAdapter implements LlmAdapterInterface
{
    public function send(LlmProvider $provider, string $systemPrompt, array $messages, array $mcpTools, ?callable $onHeartbeat = null): LlmResponse
    {
        // Mock fallback for test keys
        if (str_starts_with($provider->api_key, 'sk-mock') || empty($provider->api_key)) {
            $lastUser = collect($messages)->where('role', 'user')->last();
            $reply = "Hello! I am your Company AI Agent. I received your request: '".($lastUser['content'] ?? '')."'. (Note: Active LLM provider is using a mock key. Update your API credentials in the Admin Console to connect to live OpenAI, Claude, or Gemini.)";

            LlmLog::record(
                provider: $provider,
                endpoint: 'mock://local-openai-simulator',
                httpMethod: 'MOCK',
                requestPayload: ['messages' => $messages],
                statusCode: 200,
                responseBody: json_encode(['mock_reply' => $reply]),
                latencyMs: 10,
                isSuccess: true,
                errorMessage: null,
                promptTokens: 50,
                completionTokens: 40
            );

            return new LlmResponse(
                content: $reply,
                promptTokens: 50,
                completionTokens: 40
            );
        }

        $formattedMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $msg) {
            $entry = [
                'role' => $msg['role'],
                'content' => $msg['content'] ?? '',
            ];
            if ($msg['role'] === 'tool') {
                $entry['tool_call_id'] = $msg['tool_call_id'] ?? '';
                $entry['content'] = is_string($msg['content'] ?? '') ? $msg['content'] : json_encode($msg['content'] ?? '');
            }
            if ($msg['role'] === 'assistant') {
                if (! empty($msg['tool_calls'])) {
                    $cleanToolCalls = [];
                    foreach ($msg['tool_calls'] as $tc) {
                        $funcName = $tc['function']['name'] ?? $tc['name'] ?? '';
                        $rawArgs = $tc['function']['arguments'] ?? $tc['arguments'] ?? [];
                        $argsJson = is_string($rawArgs) ? $rawArgs : json_encode($rawArgs ?: (object) []);

                        $cleanToolCalls[] = [
                            'id' => $tc['id'] ?? 'call_'.uniqid(),
                            'type' => 'function',
                            'function' => [
                                'name' => $funcName,
                                'arguments' => $argsJson,
                            ],
                        ];
                    }
                    $entry['tool_calls'] = $cleanToolCalls;
                    $entry['content'] = ! empty($msg['content']) ? $msg['content'] : null;
                } else {
                    $entry['content'] = ! empty($msg['content']) ? $msg['content'] : ' ';
                }
            }
            $formattedMessages[] = $entry;
        }

        $tools = [];
        foreach ($mcpTools as $t) {
            $tools[] = [
                'type' => 'function',
                'function' => $t['function'],
            ];
        }

        $payload = [
            'model' => $provider->model,
            'messages' => $formattedMessages,
            'temperature' => (float) $provider->temperature,
        ];

        if ($provider->max_tokens) {
            $payload['max_tokens'] = (int) $provider->max_tokens;
        }

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $baseUrl = rtrim($provider->base_url ?: 'https://api.openai.com/v1', '/');
        $endpoint = $baseUrl.'/chat/completions';

        $lastHeartbeat = microtime(true);
        $client = Http::withHeaders([
            'Authorization' => 'Bearer '.$provider->api_key,
            'Content-Type' => 'application/json',
        ])->timeout(300)->connectTimeout(15);

        if ($onHeartbeat) {
            $client = $client->withOptions([
                'progress' => function () use (&$lastHeartbeat, $onHeartbeat) {
                    if ((microtime(true) - $lastHeartbeat) >= 2.0) {
                        $lastHeartbeat = microtime(true);
                        $onHeartbeat();
                    }
                },
            ]);
        }

        $startTime = microtime(true);
        try {
            $response = $client->post($endpoint, $payload);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $rawBody = $response->body();

            if (! $response->successful()) {
                $errBody = $response->body();
                $errJson = $response->json();
                $errDetail = $errJson['error']['message'] ?? $errBody;
                Log::error("OpenAI API Error ({$statusCode}): {$errDetail}");

                LlmLog::record(
                    provider: $provider,
                    endpoint: $endpoint,
                    httpMethod: 'POST',
                    requestPayload: $payload,
                    statusCode: $statusCode,
                    responseBody: $rawBody,
                    latencyMs: $latencyMs,
                    isSuccess: false,
                    errorMessage: "OpenAI API Error ({$statusCode}): {$errDetail}",
                );

                return new LlmResponse(
                    errorMessage: "OpenAI API Error ({$statusCode}): {$errDetail}"
                );
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (! $choice) {
                LlmLog::record(
                    provider: $provider,
                    endpoint: $endpoint,
                    httpMethod: 'POST',
                    requestPayload: $payload,
                    statusCode: $statusCode,
                    responseBody: $rawBody,
                    latencyMs: $latencyMs,
                    isSuccess: false,
                    errorMessage: 'Empty response returned by OpenAI API.',
                );

                return new LlmResponse(errorMessage: 'Empty response returned by OpenAI API.');
            }

            $usage = $data['usage'] ?? [];
            $message = $choice['message'] ?? [];

            $toolCalls = [];
            if (! empty($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $tc) {
                    $rawArgs = $tc['function']['arguments'] ?? '{}';
                    $args = is_array($rawArgs) ? $rawArgs : json_decode($rawArgs, true) ?? [];
                    $toolCalls[] = [
                        'id' => $tc['id'] ?? 'call_'.uniqid(),
                        'name' => $tc['function']['name'] ?? '',
                        'arguments' => $args,
                    ];
                }
            }

            $promptTokens = (int) ($usage['prompt_tokens'] ?? 0);
            $completionTokens = (int) ($usage['completion_tokens'] ?? 0);

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
                content: $message['content'] ?? null,
                toolCalls: $toolCalls,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens
            );
        } catch (Exception $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            Log::error('OpenAiAdapter Exception: '.$e->getMessage());

            LlmLog::record(
                provider: $provider,
                endpoint: $endpoint,
                httpMethod: 'POST',
                requestPayload: $payload,
                statusCode: 0,
                responseBody: null,
                latencyMs: $latencyMs,
                isSuccess: false,
                errorMessage: 'Connection failed: '.$e->getMessage(),
            );

            return new LlmResponse(errorMessage: $e->getMessage());
        }
    }
}
