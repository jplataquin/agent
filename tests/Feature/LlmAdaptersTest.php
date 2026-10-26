<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\LlmProvider;
use App\Models\User;
use App\Services\Llm\AnthropicAdapter;
use App\Services\Llm\GeminiAdapter;
use App\Services\Llm\LlmAdapterFactory;
use App\Services\Llm\OpenAiAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmAdaptersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_adapter_factory_resolves_correct_adapters()
    {
        $factory = app(LlmAdapterFactory::class);

        $openAiProvider = new LlmProvider(['provider_type' => 'openai']);
        $this->assertInstanceOf(OpenAiAdapter::class, $factory->make($openAiProvider));

        $anthropicProvider = new LlmProvider(['provider_type' => 'anthropic']);
        $this->assertInstanceOf(AnthropicAdapter::class, $factory->make($anthropicProvider));

        $geminiProvider = new LlmProvider(['provider_type' => 'gemini']);
        $this->assertInstanceOf(GeminiAdapter::class, $factory->make($geminiProvider));
    }

    public function test_anthropic_adapter_sends_messages_and_extracts_tokens_and_tool_calls()
    {
        $provider = LlmProvider::create([
            'name' => 'Claude 3.5 Sonnet',
            'provider_type' => 'anthropic',
            'api_key' => 'anthropic_live_test_key_123',
            'model' => 'claude-3-5-sonnet-20241022',
            'temperature' => 0.5,
            'max_tokens' => 1024,
            'is_default' => false,
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_123',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_01A',
                        'name' => 'search_internal_docs',
                        'input' => ['query' => 'expense reimbursement policy'],
                    ],
                ],
                'usage' => [
                    'input_tokens' => 120,
                    'output_tokens' => 45,
                ],
            ], 200),
        ]);

        $adapter = app(AnthropicAdapter::class);
        $response = $adapter->send(
            $provider,
            'System Prompt Test',
            [['role' => 'user', 'content' => 'How do I submit expenses?']],
            [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'search_internal_docs',
                        'description' => 'Search company docs',
                        'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
                    ],
                    'server_id' => 1,
                ],
            ]
        );

        $this->assertFalse($response->isError());
        $this->assertTrue($response->hasToolCalls());
        $this->assertEquals(120, $response->promptTokens);
        $this->assertEquals(45, $response->completionTokens);
        $this->assertEquals('search_internal_docs', $response->toolCalls[0]['name']);
        $this->assertEquals('expense reimbursement policy', $response->toolCalls[0]['arguments']['query']);
    }

    public function test_gemini_adapter_sends_contents_and_extracts_tokens()
    {
        $provider = LlmProvider::create([
            'name' => 'Google Gemini 1.5 Pro',
            'provider_type' => 'gemini',
            'api_key' => 'gemini_live_test_key_xyz',
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7,
            'is_default' => false,
            'is_active' => true,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'According to company guidelines, file expenses within 30 days.'],
                            ],
                            'role' => 'model',
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 85,
                    'candidatesTokenCount' => 20,
                    'totalTokenCount' => 105,
                ],
            ], 200),
        ]);

        $adapter = app(GeminiAdapter::class);
        $response = $adapter->send(
            $provider,
            'You are a corporate assistant.',
            [['role' => 'user', 'content' => 'What is the expense deadline?']],
            []
        );

        $this->assertFalse($response->isError());
        $this->assertEquals('According to company guidelines, file expenses within 30 days.', $response->content);
        $this->assertEquals(85, $response->promptTokens);
        $this->assertEquals(20, $response->completionTokens);
    }

    public function test_ai_agent_service_end_to_end_with_gemini_provider()
    {
        // Set Gemini as default provider
        LlmProvider::query()->update(['is_default' => false]);
        $geminiProvider = LlmProvider::create([
            'name' => 'Default Gemini Provider',
            'provider_type' => 'gemini',
            'api_key' => 'gemini_test_key',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'is_default' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Gemini Assistant response for your company inquiry.'],
                            ],
                            'role' => 'model',
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 60,
                    'candidatesTokenCount' => 25,
                ],
            ], 200),
        ]);

        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Gemini Chat Test',
        ]);

        $session->messages()->create([
            'role' => 'user',
            'content' => 'Tell me about our company core values.',
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/message", [
            'message' => 'Tell me more about it',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ai_response.content', 'Gemini Assistant response for your company inquiry.');

        $this->assertDatabaseHas('token_metrics', [
            'provider' => 'Default Gemini Provider',
            'model' => 'gemini-1.5-flash',
            'prompt_tokens' => 60,
            'completion_tokens' => 25,
        ]);
    }

    public function test_gemini_adapter_sanitizes_openai_base_url_if_present()
    {
        $provider = LlmProvider::create([
            'name' => 'Mistaken Base URL Gemini',
            'provider_type' => 'gemini',
            'api_key' => 'gemini_test_key_live',
            'model' => 'gemini-1.5-flash',
            'base_url' => 'https://api.openai.com/v1',
            'temperature' => 0.7,
            'is_default' => false,
            'is_active' => true,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Correctly routed to Gemini despite OpenAI base URL.'],
                            ],
                            'role' => 'model',
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 10,
                ],
            ], 200),
        ]);

        $adapter = app(GeminiAdapter::class);
        $response = $adapter->send($provider, 'system', [['role' => 'user', 'content' => 'hi']], []);

        $this->assertFalse($response->isError());
        $this->assertEquals('Correctly routed to Gemini despite OpenAI base URL.', $response->content);
    }

    public function test_admin_llm_provider_test_connection_routes_to_gemini()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $provider = LlmProvider::create([
            'name' => 'Production Gemini',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyTestApiKeyForGemini',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'is_default' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models?key=AIzaSyTestApiKeyForGemini' => Http::response([
                'models' => [
                    ['name' => 'models/gemini-1.5-flash'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->post("/admin/llm-providers/{$provider->id}/test");
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Google Gemini', session('success'));
    }

    public function test_admin_llm_provider_store_clears_openai_base_url_for_gemini()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->post('/admin/llm-providers', [
            'name' => 'My Google Gemini 1.5',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSy1234567890',
            'model' => 'gpt-4o', // Submitted accidentally from OpenAI default
            'base_url' => 'https://api.openai.com/v1', // Submitted accidentally from OpenAI default
            'temperature' => 0.7,
            'is_default' => '1',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/llm-providers');
        $this->assertDatabaseHas('llm_providers', [
            'name' => 'My Google Gemini 1.5',
            'provider_type' => 'gemini',
            'model' => 'gemini-1.5-flash', // Auto-corrected to gemini
            'base_url' => null, // Auto-cleared OpenAI base URL
            'is_default' => true,
        ]);
    }

    public function test_gemini_adapter_formats_uppercase_schema_and_alternates_roles()
    {
        $provider = LlmProvider::create([
            'name' => 'Test Gemini Tool Formatting',
            'provider_type' => 'gemini',
            'api_key' => 'live_gemini_key_123',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'is_default' => false,
            'is_active' => true,
        ]);

        $recordedPayload = null;
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function ($request) use (&$recordedPayload) {
                $recordedPayload = $request->data();

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Schema formatted successfully.'],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $adapter = app(GeminiAdapter::class);
        $adapter->send(
            $provider,
            'System instructions',
            [
                ['role' => 'user', 'content' => 'First user question'],
                ['role' => 'user', 'content' => 'Second immediate user follow-up'], // Consecutive user turns
            ],
            [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'test_tool',
                        'description' => 'Test tool desc',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'query' => ['type' => 'string'],
                            ],
                            'required' => ['query'],
                        ],
                    ],
                ],
            ]
        );

        $this->assertNotNull($recordedPayload);
        // Schema types must be uppercase for Gemini REST API
        $toolDecl = $recordedPayload['tools'][0]['functionDeclarations'][0];
        $this->assertEquals('OBJECT', $toolDecl['parameters']['type']);
        $this->assertEquals('STRING', $toolDecl['parameters']['properties']->query['type']);

        // Turns must be merged so roles alternate strictly
        $this->assertCount(1, $recordedPayload['contents']);
        $this->assertEquals('user', $recordedPayload['contents'][0]['role']);
        $this->assertCount(2, $recordedPayload['contents'][0]['parts']);
    }

    public function test_ai_agent_service_emits_error_when_adapter_fails()
    {
        LlmProvider::query()->update(['is_default' => false]);
        $failingProvider = LlmProvider::create([
            'name' => 'Failing Gemini',
            'provider_type' => 'gemini',
            'api_key' => 'invalid_gemini_key',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'is_default' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'API key not valid. Please pass a valid API key.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Test Error Output',
        ]);

        $emittedChunk = null;
        $onChunk = function ($text) use (&$emittedChunk) {
            $emittedChunk = $text;
        };

        $service = app(\App\Services\AiAgentService::class);
        $result = $service->generateResponse($session, $onChunk);

        $this->assertNotNull($emittedChunk);
        $this->assertStringContainsString('API key not valid', $emittedChunk);
        $this->assertStringContainsString('AI Provider Error', $result['content']);

        // Assistant error message saved to database
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'assistant',
        ]);
    }

    public function test_llm_log_records_request_with_masked_api_key()
    {
        $provider = LlmProvider::create([
            'name' => 'Masking Test Provider',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSySecretApiKey123',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'is_default' => false,
            'is_active' => true,
        ]);

        $log = \App\Models\LlmLog::record(
            provider: $provider,
            endpoint: 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=AIzaSySecretApiKey123',
            httpMethod: 'POST',
            requestPayload: ['test' => 'data'],
            statusCode: 200,
            responseBody: '{"candidates": []}',
            latencyMs: 120,
            isSuccess: true,
            errorMessage: null
        );

        $this->assertStringNotContainsString('AIzaSySecretApiKey123', $log->endpoint);
        $this->assertStringContainsString('••••••••', $log->endpoint);
        $this->assertEquals(200, $log->status_code);
        $this->assertEquals(120, $log->latency_ms);
    }

    public function test_admin_can_view_llm_provider_diagnostic_logs()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $provider = LlmProvider::first();

        \App\Models\LlmLog::record(
            provider: $provider,
            endpoint: 'https://api.openai.com/v1/chat/completions',
            httpMethod: 'POST',
            requestPayload: ['messages' => []],
            statusCode: 200,
            responseBody: '{"choices": []}',
            latencyMs: 95,
            isSuccess: true
        );

        $response = $this->actingAs($admin)->get("/admin/llm-providers/{$provider->id}/logs");
        $response->assertStatus(200);
        $response->assertSee('Diagnostic Logs:');
        $response->assertSee('95 ms');
    }

    public function test_admin_can_execute_live_test_prompt_and_clear_logs()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $provider = LlmProvider::first();

        // 1. Execute live diagnostic test prompt
        $response = $this->actingAs($admin)->post("/admin/llm-providers/{$provider->id}/test-prompt", [
            'prompt' => 'Diagnostic test message',
        ]);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('llm_logs', [
            'llm_provider_id' => $provider->id,
        ]);

        // 2. Clear logs
        $deleteResponse = $this->actingAs($admin)->delete("/admin/llm-providers/{$provider->id}/logs");
        $deleteResponse->assertSessionHas('success');
        $this->assertEquals(0, $provider->logs()->count());
    }
}
