<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\LlmProvider;
use App\Models\User;
use App\Services\AiAgentService;
use App\Services\Llm\LlmAdapterInterface;
use App\Services\Llm\LlmAdapterFactory;
use App\Services\Llm\LlmResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatWrapUpTest extends TestCase
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

    public function test_llm_provider_has_wrap_up_timeout_default_and_can_be_configured()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        // Check default value on creation via database
        $provider = LlmProvider::create([
            'name' => 'Test Provider',
            'provider_type' => 'gemini',
            'api_key' => 'test-key',
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->assertEquals(60, $provider->fresh()->wrap_up_timeout);

        // Update via Admin Controller
        $response = $this->actingAs($admin)->put(route('admin.llm-providers.update', $provider), [
            'name' => 'Updated Provider',
            'provider_type' => 'gemini',
            'api_key' => 'new-key',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.5,
            'wrap_up_timeout' => 45,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.llm-providers.index'));
        $this->assertEquals(45, $provider->fresh()->wrap_up_timeout);
    }

    public function test_user_can_trigger_wrap_up_endpoint()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Test Session',
        ]);

        $this->assertFalse(Cache::has('chat_wrap_up_' . $session->id));

        $response = $this->actingAs($user)->postJson(route('chat.wrap-up', $session));

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'message' => 'Wrap up signal sent successfully.',
        ]);

        $this->assertTrue(Cache::has('chat_wrap_up_' . $session->id));
    }

    public function test_user_cannot_trigger_wrap_up_for_another_user_session()
    {
        $user1 = User::where('role', User::ROLE_REGULAR)->first();
        $user2 = User::factory()->create(['role' => User::ROLE_REGULAR]);

        $session = ChatSession::create([
            'user_id' => $user1->id,
            'title' => 'User 1 Session',
        ]);

        $response = $this->actingAs($user2)->postJson(route('chat.wrap-up', $session));
        $response->assertStatus(403);
    }

    public function test_ai_agent_service_forces_synthesis_when_wrap_up_flag_is_present()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Agent Session',
        ]);

        $session->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => 'Please analyze everything and search web.',
        ]);

        // Place wrap-up signal in Cache
        Cache::put('chat_wrap_up_' . $session->id, true);

        // Mock adapter to verify that synthesis prompt is received with wrap-up notice
        $mockAdapter = $this->createMock(LlmAdapterInterface::class);
        $mockAdapter->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->stringContains('Notice: The user requested an early wrap-up. Synthesize all findings'),
                $this->anything(),
                $this->equalTo([])
            )
            ->willReturn(new LlmResponse(
                content: 'Synthesized response early as requested.',
                promptTokens: 10,
                completionTokens: 20
            ));

        $mockFactory = $this->createMock(LlmAdapterFactory::class);
        $mockFactory->method('make')->willReturn($mockAdapter);

        $service = new AiAgentService(
            app(\App\Services\McpService::class),
            $mockFactory
        );

        $result = $service->generateResponse($session);

        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('Synthesized response early as requested.', $result['content']);
        $this->assertFalse(Cache::has('chat_wrap_up_' . $session->id));
    }
}
