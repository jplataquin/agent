<?php

namespace App\Services\Llm;

use App\Models\LlmProvider;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiAdapter implements LlmAdapterInterface
{
    public function send(LlmProvider $provider, string $systemPrompt, array $messages, array $mcpTools): LlmResponse
    {
        // Mock fallback for test keys
        if (str_starts_with($provider->api_key, 'sk-mock') || empty($provider->api_key)) {
            $lastUser = collect($messages)->where('role', 'user')->last();
            $reply = "Hello! I am your Company AI Agent. I received your request: '".($lastUser['content'] ?? '')."'. (Note: Active LLM provider is using a mock key. Update your API credentials in the Admin Console to connect to live OpenAI, Claude, or Gemini.)";

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
            $entry = ['role' => $msg['role']];
            if ($msg['role'] === 'tool') {
                $entry['content'] = $msg['content'] ?? '';
                $entry['tool_call_id'] = $msg['tool_call_id'] ?? 'call_1';
            } elseif ($msg['role'] === 'assistant') {
                $entry['content'] = $msg['content'];
                if (! empty($msg['tool_calls'])) {
                    $entry['tool_calls'] = $msg['tool_calls'];
                }
            } else {
                $entry['content'] = $msg['content'] ?? '';
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

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$provider->api_key,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($endpoint, $payload);

            if (! $response->successful()) {
                return new LlmResponse(
                    errorMessage: "OpenAI API Error ({$response->status()}): ".$response->body()
                );
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (! $choice) {
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

            return new LlmResponse(
                content: $message['content'] ?? null,
                toolCalls: $toolCalls,
                promptTokens: (int) ($usage['prompt_tokens'] ?? 0),
                completionTokens: (int) ($usage['completion_tokens'] ?? 0)
            );
        } catch (Exception $e) {
            Log::error('OpenAiAdapter Exception: '.$e->getMessage());

            return new LlmResponse(errorMessage: $e->getMessage());
        }
    }
}
