<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\LlmProvider;
use App\Models\User;
use App\Services\AiAgentService;
use App\Services\Llm\AnthropicAdapter;
use App\Services\Llm\GeminiAdapter;
use App\Services\Llm\OpenAiAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatStreamingResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test that stream returns 2KB proxy padding preamble, connected status, and SSE chunks.
     */
    public function test_stream_returns_preamble_and_keepalive_headers(): void
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Resilience Test',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}/stream?message=Please+provide+a+detailed+response");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $response->assertHeader('Cache-Control', 'no-cache, private');
        $response->assertHeader('X-Accel-Buffering', 'no');

        // Capture streamed content
        $content = $response->streamedContent();

        // 1. Verify 2KB proxy buffer bypass padding
        $this->assertStringContainsString(': '.str_repeat(' ', 100), $content);

        // 2. Verify connected preamble
        $this->assertStringContainsString(": connected\n\n", $content);

        // 3. Verify chunk data and completion
        $this->assertStringContainsString('"chunk":', $content);
        $this->assertStringContainsString('"done":true', $content);
    }

    /**
     * Test that OpenAI adapter accepts onHeartbeat and successfully executes without timeout errors.
     */
    public function test_openai_adapter_accepts_heartbeat_and_runs_long_request(): void
    {
        $provider = LlmProvider::create([
            'name' => 'Live OpenAI Test',
            'provider_type' => 'openai',
            'api_key' => 'sk-live-test-key-for-heartbeat',
            'model' => 'gpt-4o',
            'temperature' => 0.7,
            'is_default' => false,
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Here is a very long and detailed response from OpenAI that took considerable reasoning.',
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 200,
                ],
            ], 200),
        ]);

        $adapter = app(OpenAiAdapter::class);
        $heartbeatCalled = false;
        $onHeartbeat = function () use (&$heartbeatCalled) {
            $heartbeatCalled = true;
        };

        $result = $adapter->send($provider, 'System prompt', [
            ['role' => 'user', 'content' => 'Long prompt'],
        ], [], $onHeartbeat);

        $this->assertFalse($result->isError());
        $this->assertStringContainsString('very long and detailed response', $result->content);
    }

    /**
     * Test that Gemini adapter accepts onHeartbeat and handles long responses.
     */
    public function test_gemini_adapter_accepts_heartbeat_and_runs_long_request(): void
    {
        $provider = LlmProvider::create([
            'name' => 'Live Gemini Test',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyLiveTestKeyForHeartbeat',
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
                                ['text' => 'Here is a thorough response from Google Gemini.'],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 80,
                    'candidatesTokenCount' => 150,
                ],
            ], 200),
        ]);

        $adapter = app(GeminiAdapter::class);
        $heartbeatCalled = false;
        $onHeartbeat = function () use (&$heartbeatCalled) {
            $heartbeatCalled = true;
        };

        $result = $adapter->send($provider, 'System prompt', [
            ['role' => 'user', 'content' => 'Detailed research topic'],
        ], [], $onHeartbeat);

        $this->assertFalse($result->isError());
        $this->assertStringContainsString('thorough response from Google Gemini', $result->content);
    }

    /**
     * Test that Anthropic adapter accepts onHeartbeat and handles long responses.
     */
    public function test_anthropic_adapter_accepts_heartbeat_and_runs_long_request(): void
    {
        $provider = LlmProvider::create([
            'name' => 'Live Claude Test',
            'provider_type' => 'anthropic',
            'api_key' => 'anthropic_live_test_key_heartbeat',
            'model' => 'claude-3-5-sonnet-20241022',
            'temperature' => 0.7,
            'is_default' => false,
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_heartbeat_123',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => 'Here is an exhaustive essay from Claude.'],
                ],
                'usage' => [
                    'input_tokens' => 60,
                    'output_tokens' => 180,
                ],
            ], 200),
        ]);

        $adapter = app(AnthropicAdapter::class);
        $heartbeatCalled = false;
        $onHeartbeat = function () use (&$heartbeatCalled) {
            $heartbeatCalled = true;
        };

        $result = $adapter->send($provider, 'System prompt', [
            ['role' => 'user', 'content' => 'Long prompt requiring thoughtful analysis'],
        ], [], $onHeartbeat);

        $this->assertFalse($result->isError());
        $this->assertStringContainsString('exhaustive essay from Claude', $result->content);
    }
}
