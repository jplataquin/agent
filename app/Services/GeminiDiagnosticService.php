<?php

namespace App\Services;

use App\Models\LlmProvider;
use Exception;
use Illuminate\Support\Facades\Http;

class GeminiDiagnosticService
{
    /**
     * Run a comprehensive live diagnostic suite against a Gemini model.
     *
     * @param  array{
     *     provider_id?: ?int,
     *     api_key?: ?string,
     *     model?: ?string,
     *     base_url?: ?string,
     *     test_mode?: ?string,
     *     thinking_level?: ?string
     * }  $params
     * @return array<string, mixed>
     */
    public function runDiagnostics(array $params = []): array
    {
        // 1. Resolve Provider and Credentials
        $provider = null;
        if (! empty($params['provider_id'])) {
            $provider = LlmProvider::find($params['provider_id']);
        }
        if (! $provider) {
            $provider = LlmProvider::where('provider_type', 'gemini')->where('is_default', true)->first()
                ?: LlmProvider::where('provider_type', 'gemini')->first();
        }

        $apiKey = $params['api_key'] ?? ($provider?->api_key ?? '');
        $rawModel = $params['model'] ?? ($provider?->model ?? 'gemini-3.8-flash');
        $model = preg_replace('/^models\//', '', trim($rawModel ?: 'gemini-3.8-flash'));
        $baseUrl = rtrim($params['base_url'] ?? ($provider?->base_url ?: 'https://generativelanguage.googleapis.com'), '/');
        if (str_contains($baseUrl, 'api.openai.com')) {
            $baseUrl = 'https://generativelanguage.googleapis.com';
        }

        $isMock = empty($apiKey) || str_starts_with($apiKey, 'sk-mock') || str_starts_with($apiKey, 'mock-');
        $thinkingLevel = $params['thinking_level'] ?? 'high';

        $report = [
            'timestamp' => now()->toIso8601String(),
            'target_model' => $model,
            'api_endpoint' => "{$baseUrl}/v1beta/models/{$model}:generateContent",
            'is_mock_key' => $isMock,
            'provider_name' => $provider?->name ?? 'Custom / Ad-hoc Provider',
            'server_environment' => [
                'php_version' => PHP_VERSION,
                'curl_version' => function_exists('curl_version') ? (curl_version()['version'] ?? 'N/A') : 'N/A',
                'openssl_version' => OPENSSL_VERSION_TEXT,
                'os' => PHP_OS_FAMILY,
            ],
            'checks' => [
                'connectivity' => null,
                'tool_call' => null,
                'round_trip' => null,
            ],
            'summary' => [
                'all_passed' => true,
                'issues' => [],
                'recommendations' => [],
            ],
        ];

        // -------------------------------------------------------------
        // Check 1: Simple Connectivity Test
        // -------------------------------------------------------------
        $report['checks']['connectivity'] = $this->testConnectivity($baseUrl, $model, $apiKey, $isMock, $provider);

        if ($report['checks']['connectivity']['status'] !== 'passed') {
            $report['summary']['all_passed'] = false;
            $report['summary']['issues'][] = 'Basic Gemini API generation request failed: '.($report['checks']['connectivity']['error'] ?? 'Unknown error');
            $report['summary']['recommendations'][] = 'Verify that your Gemini API key has access to model "'.$model.'" and billing is enabled in Google AI Studio / Google Cloud Console.';

            return $report;
        }

        // -------------------------------------------------------------
        // Check 2: Tool Call & Thought Signature Inspection
        // -------------------------------------------------------------
        $report['checks']['tool_call'] = $this->testToolCall($baseUrl, $model, $apiKey, $isMock, $thinkingLevel, $provider);

        if ($report['checks']['tool_call']['status'] !== 'passed') {
            $report['summary']['all_passed'] = false;
            $report['summary']['issues'][] = 'Gemini did not trigger the requested tool or returned an error: '.($report['checks']['tool_call']['error'] ?? 'Unknown error');
            $report['summary']['recommendations'][] = 'Ensure your function declarations use uppercase OpenAPI schema types (OBJECT, STRING) required by Gemini.';

            return $report;
        }

        // -------------------------------------------------------------
        // Check 3: Stateful Multi-Turn Round Trip (The Thought Signature Verification)
        // -------------------------------------------------------------
        $toolCallData = $report['checks']['tool_call']['tool_call_data'] ?? null;
        $report['checks']['round_trip'] = $this->testRoundTrip($baseUrl, $model, $apiKey, $isMock, $toolCallData, $provider);

        if ($report['checks']['round_trip']['status'] !== 'passed') {
            $report['summary']['all_passed'] = false;
            $err = $report['checks']['round_trip']['error'] ?? 'Unknown error';
            $report['summary']['issues'][] = "Gemini round-trip verification failed: {$err}";

            if (str_contains(strtolower($err), 'thought_signature')) {
                $report['summary']['recommendations'][] = 'CRITICAL: Google Gemini API rejected the function response due to a missing or invalid thought_signature. Ensure thought_signature is strictly echoed back in preceding functionCall parts.';
            } else {
                $report['summary']['recommendations'][] = 'Inspect the round_trip request payload below to verify role alternation (user -> model -> user) and payload structure.';
            }
        }

        return $report;
    }

    /**
     * Test single-turn basic connectivity.
     */
    protected function testConnectivity(string $baseUrl, string $model, string $apiKey, bool $isMock, ?LlmProvider $provider): array
    {
        $endpoint = "{$baseUrl}/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'Respond strictly with: "Gemini 3 Diagnostic OK" and specify your exact model name.'],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 100,
            ],
        ];

        if ($isMock) {
            return [
                'status' => 'passed',
                'status_code' => 200,
                'latency_ms' => 45,
                'reply' => "Gemini 3 Diagnostic OK (Model: {$model} via mock responder)",
                'error' => null,
                'request_payload' => $payload,
                'raw_response' => [
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => "Gemini 3 Diagnostic OK (Model: {$model})"]],
                                'role' => 'model',
                            ],
                        ],
                    ],
                    'usageMetadata' => ['promptTokenCount' => 25, 'candidatesTokenCount' => 15],
                ],
            ];
        }

        $startTime = microtime(true);
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($endpoint, $payload);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $data = $response->json() ?? [];

            if (! $response->successful()) {
                $errMsg = $data['error']['message'] ?? $response->body();

                return [
                    'status' => 'failed',
                    'status_code' => $statusCode,
                    'latency_ms' => $latencyMs,
                    'error' => "HTTP {$statusCode}: {$errMsg}",
                    'request_payload' => $payload,
                    'raw_response' => $data,
                ];
            }

            $candidate = $data['candidates'][0] ?? null;
            $parts = $candidate['content']['parts'] ?? [];
            $text = '';
            foreach ($parts as $p) {
                if (! empty($p['text']) && empty($p['thought'])) {
                    $text .= $p['text'];
                }
            }

            return [
                'status' => 'passed',
                'status_code' => $statusCode,
                'latency_ms' => $latencyMs,
                'reply' => trim($text) ?: '(No text parts returned)',
                'error' => null,
                'usage' => $data['usageMetadata'] ?? [],
                'request_payload' => $payload,
                'raw_response' => $data,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'status_code' => 0,
                'latency_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'error' => 'Connection failed: '.$e->getMessage(),
                'request_payload' => $payload,
                'raw_response' => null,
            ];
        }
    }

    /**
     * Test tool declaration and inspect model thought signature.
     */
    protected function testToolCall(string $baseUrl, string $model, string $apiKey, bool $isMock, string $thinkingLevel, ?LlmProvider $provider): array
    {
        $endpoint = "{$baseUrl}/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'list_projects',
                        'description' => 'Retrieve a list of company construction and engineering projects.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => (object) [
                                'status' => [
                                    'type' => 'STRING',
                                    'description' => 'Project status filter: ACTV (Active), PEND (Pending), or COMP (Completed)',
                                ],
                            ],
                            'required' => ['status'],
                        ],
                    ],
                ],
            ],
        ];

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'We need to check our projects. Please call the list_projects tool with status "ACTV".'],
                    ],
                ],
            ],
            'tools' => $tools,
            'generationConfig' => [
                'temperature' => 0.1,
                'thinkingConfig' => [
                    'thinkingLevel' => $thinkingLevel,
                ],
            ],
        ];

        if ($isMock) {
            $mockSig = 'mock_thought_sig_'.bin2hex(random_bytes(16));

            return [
                'status' => 'passed',
                'status_code' => 200,
                'latency_ms' => 60,
                'tool_invoked' => 'list_projects',
                'tool_arguments' => ['status' => 'ACTV'],
                'has_thought_signature' => true,
                'thought_signature_source' => 'part.thought_signature',
                'thought_signature_length' => strlen($mockSig),
                'thought_signature_preview' => $mockSig,
                'has_reasoning_block' => true,
                'reasoning_preview' => 'Analyzing user request to list projects with ACTV status...',
                'error' => null,
                'tool_call_data' => [
                    'name' => 'list_projects',
                    'args' => ['status' => 'ACTV'],
                    'thought_signature' => $mockSig,
                ],
                'request_payload' => $payload,
                'raw_response' => [
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['thought' => true, 'text' => 'Analyzing user request to list projects with ACTV status...'],
                                    [
                                        'functionCall' => [
                                            'name' => 'list_projects',
                                            'args' => ['status' => 'ACTV'],
                                        ],
                                        'thought_signature' => $mockSig,
                                    ],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                ],
            ];
        }

        $startTime = microtime(true);
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(45)
                ->post($endpoint, $payload);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $data = $response->json() ?? [];

            if (! $response->successful()) {
                $errMsg = $data['error']['message'] ?? $response->body();

                return [
                    'status' => 'failed',
                    'status_code' => $statusCode,
                    'latency_ms' => $latencyMs,
                    'error' => "HTTP {$statusCode}: {$errMsg}",
                    'request_payload' => $payload,
                    'raw_response' => $data,
                ];
            }

            $candidate = $data['candidates'][0] ?? null;
            $parts = $candidate['content']['parts'] ?? [];

            $foundCall = null;
            $foundSig = null;
            $sigSource = null;
            $reasoningText = '';

            // Global candidate signature
            $globalSig = $candidate['thought_signature']
                ?? $candidate['thoughtSignature']
                ?? ($candidate['content']['thought_signature'] ?? null)
                ?? ($candidate['content']['thoughtSignature'] ?? null);

            foreach ($parts as $p) {
                if (! empty($p['thought'])) {
                    $reasoningText .= ($p['text'] ?? '').' ';
                }

                if (! empty($p['functionCall'])) {
                    $foundCall = $p['functionCall'];

                    if (! empty($p['thought_signature'])) {
                        $foundSig = $p['thought_signature'];
                        $sigSource = 'part.thought_signature (snake_case)';
                    } elseif (! empty($p['thoughtSignature'])) {
                        $foundSig = $p['thoughtSignature'];
                        $sigSource = 'part.thoughtSignature (camelCase)';
                    } elseif (! empty($foundCall['thought_signature'])) {
                        $foundSig = $foundCall['thought_signature'];
                        $sigSource = 'functionCall.thought_signature (snake_case)';
                    } elseif (! empty($foundCall['thoughtSignature'])) {
                        $foundSig = $foundCall['thoughtSignature'];
                        $sigSource = 'functionCall.thoughtSignature (camelCase)';
                    } elseif ($globalSig) {
                        $foundSig = $globalSig;
                        $sigSource = 'candidate.thought_signature (inherited)';
                    }
                }
            }

            if (! $foundCall) {
                return [
                    'status' => 'failed',
                    'status_code' => $statusCode,
                    'latency_ms' => $latencyMs,
                    'error' => 'Model did not trigger functionCall "list_projects". Returned standard text instead.',
                    'request_payload' => $payload,
                    'raw_response' => $data,
                ];
            }

            return [
                'status' => 'passed',
                'status_code' => $statusCode,
                'latency_ms' => $latencyMs,
                'tool_invoked' => $foundCall['name'] ?? '',
                'tool_arguments' => (array) ($foundCall['args'] ?? []),
                'has_thought_signature' => ! empty($foundSig),
                'thought_signature_source' => $sigSource ?: 'None',
                'thought_signature_length' => $foundSig ? strlen($foundSig) : 0,
                'thought_signature_preview' => $foundSig ? (substr($foundSig, 0, 32).'...') : '(none)',
                'has_reasoning_block' => ! empty(trim($reasoningText)),
                'reasoning_preview' => ! empty(trim($reasoningText)) ? substr(trim($reasoningText), 0, 140).'...' : '(none)',
                'error' => null,
                'tool_call_data' => [
                    'name' => $foundCall['name'] ?? 'list_projects',
                    'args' => (array) ($foundCall['args'] ?? []),
                    'thought_signature' => $foundSig ?: 'skip_thought_signature_validator',
                ],
                'request_payload' => $payload,
                'raw_response' => $data,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'status_code' => 0,
                'latency_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'error' => 'Tool call test failed: '.$e->getMessage(),
                'request_payload' => $payload,
                'raw_response' => null,
            ];
        }
    }

    /**
     * Test multi-turn round-trip: return tool execution result and inspect Gemini 3 response.
     */
    protected function testRoundTrip(string $baseUrl, string $model, string $apiKey, bool $isMock, ?array $toolCallData, ?LlmProvider $provider): array
    {
        $endpoint = "{$baseUrl}/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $fnName = $toolCallData['name'] ?? 'list_projects';
        $fnArgs = $toolCallData['args'] ?? ['status' => 'ACTV'];
        $sig = $toolCallData['thought_signature'] ?? 'skip_thought_signature_validator';

        // Build alternating 3-turn state:
        // Turn 0: user message
        // Turn 1: model tool call with thought_signature
        // Turn 2: user functionResponse with execution output
        $modelPart = [
            'functionCall' => [
                'name' => $fnName,
                'args' => (object) $fnArgs,
            ],
            'thought_signature' => $sig,
        ];

        $mockOutput = json_encode([
            'projects' => [
                ['id' => 101, 'name' => 'Metro Expressway Expansion', 'status' => 'ACTV'],
                ['id' => 102, 'name' => 'Seaport Container Yard Phase 2', 'status' => 'ACTV'],
            ],
        ]);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'We need to check our projects. Please call the list_projects tool with status "ACTV".'],
                    ],
                ],
                [
                    'role' => 'model',
                    'parts' => [
                        $modelPart,
                    ],
                ],
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $fnName,
                                'response' => [
                                    'content' => $mockOutput,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ];

        if ($isMock) {
            return [
                'status' => 'passed',
                'status_code' => 200,
                'latency_ms' => 70,
                'synthesized_response' => "I retrieved our active projects:\n- **Metro Expressway Expansion** (ID: 101)\n- **Seaport Container Yard Phase 2** (ID: 102)",
                'error' => null,
                'request_payload' => $payload,
                'raw_response' => [
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => "I retrieved our active projects:\n- **Metro Expressway Expansion** (ID: 101)\n- **Seaport Container Yard Phase 2** (ID: 102)"],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                ],
            ];
        }

        $startTime = microtime(true);
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(45)
                ->post($endpoint, $payload);

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $data = $response->json() ?? [];

            if (! $response->successful()) {
                $errMsg = $data['error']['message'] ?? $response->body();

                return [
                    'status' => 'failed',
                    'status_code' => $statusCode,
                    'latency_ms' => $latencyMs,
                    'error' => "HTTP {$statusCode}: {$errMsg}",
                    'request_payload' => $payload,
                    'raw_response' => $data,
                ];
            }

            $candidate = $data['candidates'][0] ?? null;
            $parts = $candidate['content']['parts'] ?? [];
            $text = '';
            foreach ($parts as $p) {
                if (! empty($p['text']) && empty($p['thought'])) {
                    $text .= $p['text'];
                }
            }

            return [
                'status' => 'passed',
                'status_code' => $statusCode,
                'latency_ms' => $latencyMs,
                'synthesized_response' => trim($text) ?: '(No synthesized text parts returned)',
                'error' => null,
                'request_payload' => $payload,
                'raw_response' => $data,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'status_code' => 0,
                'latency_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'error' => 'Round-trip request failed: '.$e->getMessage(),
                'request_payload' => $payload,
                'raw_response' => null,
            ];
        }
    }
}
