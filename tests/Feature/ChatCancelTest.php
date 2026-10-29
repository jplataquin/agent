<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\AiAgentService;
use App\Services\Llm\LlmAdapterFactory;
use App\Services\Llm\LlmAdapterInterface;
use App\Services\Llm\LlmResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $appPath = dirname(__DIR__, 2) . '/app';
        $rdi = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
        $classMap = [];
        foreach ($rdi as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $rel = substr($file->getPathname(), strlen($appPath));
                $class = 'App' . str_replace('/', '\\', substr($rel, 0, -4));
                $classMap[$class] = $file->getPathname();
            }
        }
        $loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
        $loader->addClassMap($classMap);

        parent::setUp();
        $this->seed();

        config(['view.paths' => [dirname(__DIR__, 2) . '/resources/views']]);
        app()->forgetInstance('view.finder');
        \Illuminate\Support\Facades\View::clearResolvedInstances();
    }

    public function test_user_can_trigger_cancel_endpoint()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Test Session',
        ]);

        $this->assertFalse(Cache::has('chat_cancel_' . $session->id));

        $response = $this->actingAs($user)->postJson(route('chat.cancel', $session));

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'message' => 'Action cancelled successfully.',
        ]);

        $this->assertTrue(Cache::has('chat_cancel_' . $session->id));
    }

    public function test_user_cannot_cancel_another_user_session()
    {
        $user1 = User::where('role', User::ROLE_REGULAR)->first();
        $user2 = User::factory()->create(['role' => User::ROLE_REGULAR]);

        $session = ChatSession::create([
            'user_id' => $user1->id,
            'title' => 'User 1 Session',
        ]);

        $response = $this->actingAs($user2)->postJson(route('chat.cancel', $session));
        $response->assertStatus(403);
    }

    public function test_ai_agent_service_halts_immediately_when_cancel_flag_is_present()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Cancel Session',
        ]);

        $session->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => 'Long running query that will get cancelled.',
        ]);

        // Place cancellation flag in cache
        Cache::put('chat_cancel_' . $session->id, true);

        // Adapter send should never be called because cancellation is caught before the loop turn
        $mockAdapter = $this->createMock(LlmAdapterInterface::class);
        $mockAdapter->expects($this->never())->method('send');

        $mockFactory = $this->createMock(LlmAdapterFactory::class);
        $mockFactory->method('make')->willReturn($mockAdapter);

        $service = new AiAgentService(
            app(\App\Services\McpService::class),
            $mockFactory
        );

        $result = $service->generateResponse($session);

        $this->assertTrue($result['cancelled'] ?? false);
        $this->assertStringContainsString('cancelled by user', $result['content']);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => '⚠️ *[Response generation cancelled by user]*',
        ]);

        $this->assertFalse(Cache::has('chat_cancel_' . $session->id));
    }

    public function test_ai_agent_service_halts_during_tool_execution_when_cancel_is_triggered()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Tool Cancel Session',
        ]);

        $session->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => 'Execute tools but cancel midway.',
        ]);

        // Adapter returns a tool call on first call
        $mockAdapter = $this->createMock(LlmAdapterInterface::class);
        $mockAdapter->expects($this->once())
            ->method('send')
            ->willReturn(new LlmResponse(
                content: 'Calling tool',
                toolCalls: [
                    [
                        'id' => 'call_1',
                        'name' => 'test_tool',
                        'arguments' => [],
                    ]
                ]
            ));

        $mockFactory = $this->createMock(LlmAdapterFactory::class);
        $mockFactory->method('make')->willReturn($mockAdapter);

        $mockMcp = $this->createMock(\App\Services\McpService::class);
        $mockMcp->method('getAllActiveTools')->willReturn([]);
        $mockMcp->expects($this->once())
            ->method('executeTool')
            ->willReturnCallback(function () use ($session) {
                // Simulate cancel signal being received during tool execution
                Cache::put('chat_cancel_' . $session->id, true);
                return ['output' => 'tool finished'];
            });

        $service = new AiAgentService(
            $mockMcp,
            $mockFactory
        );

        $result = $service->generateResponse($session);

        $this->assertTrue($result['cancelled'] ?? false);
        $this->assertStringContainsString('cancelled by user', $result['content']);
        $this->assertFalse(Cache::has('chat_cancel_' . $session->id));
    }
}
