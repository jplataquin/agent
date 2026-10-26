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
}
