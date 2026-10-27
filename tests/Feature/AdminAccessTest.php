<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
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

    public function test_guest_is_redirected_to_login_when_accessing_admin()
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_admin_dashboard()
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();

        $response = $this->actingAs($regularUser)->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Admin Console');
    }

    public function test_admin_can_create_skill()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->post('/admin/skills', [
            'name' => 'Accounting Protocol',
            'slug' => 'accounting-protocol',
            'description' => 'Handles invoices and expense reports',
            'instructions' => 'Follow GAAP guidelines and internal procurement limits.',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/skills');
        $this->assertDatabaseHas('skills', [
            'name' => 'Accounting Protocol',
            'slug' => 'accounting-protocol',
        ]);
    }

    public function test_admin_can_register_mcp_server()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->post('/admin/mcp-servers', [
            'name' => 'ERP Inventory MCP',
            'endpoint_url' => 'https://erp.company.internal/mcp',
            'auth_type' => 'bearer',
            'auth_token' => 'secret_token_123',
            'description' => 'Real-time stock query endpoint',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/mcp-servers');
        $this->assertDatabaseHas('mcp_servers', [
            'name' => 'ERP Inventory MCP',
        ]);
    }

    public function test_admin_can_create_master_prompt_and_set_active()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->post('/admin/master-prompts', [
            'title' => 'Concise Engineering Persona',
            'content' => 'You are an AI optimized for brevity and accuracy in code reviews.',
            'is_active' => '1',
        ]);

        $response->assertRedirect('/admin/master-prompts');
        $this->assertDatabaseHas('master_prompts', [
            'title' => 'Concise Engineering Persona',
            'is_active' => true,
        ]);
    }

    public function test_navbar_renders_direct_admin_console_link_for_admin()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->get('/chat');
        $response->assertStatus(200);
        $response->assertSee(route('admin.dashboard'));
        $response->assertSee('Admin Console');
    }

    public function test_navbar_does_not_render_admin_console_link_for_regular_user()
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();

        $response = $this->actingAs($regularUser)->get('/chat');
        $response->assertStatus(200);
        $response->assertDontSee(route('admin.dashboard'));
        $response->assertDontSee('Admin Console');
    }

    public function test_admin_can_filter_llm_logs_by_chat_session_id()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $provider = \App\Models\LlmProvider::first();

        $session1 = \App\Models\ChatSession::create([
            'user_id' => $admin->id,
            'title' => 'First Session',
        ]);

        $session2 = \App\Models\ChatSession::create([
            'user_id' => $admin->id,
            'title' => 'Second Session',
        ]);

        $log1 = \App\Models\LlmLog::create([
            'llm_provider_id' => $provider->id,
            'chat_session_id' => $session1->id,
            'user_id' => $admin->id,
            'action' => 'chat_completion',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'request_payload' => ['messages' => [['role' => 'user', 'content' => 'Question 1']]],
            'response_body' => json_encode(['reply' => 'Answer 1']),
            'status_code' => 200,
            'is_success' => true,
        ]);

        $log2 = \App\Models\LlmLog::create([
            'llm_provider_id' => $provider->id,
            'chat_session_id' => $session2->id,
            'user_id' => $admin->id,
            'action' => 'chat_completion',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'request_payload' => ['messages' => [['role' => 'user', 'content' => 'Question 2']]],
            'response_body' => json_encode(['reply' => 'Answer 2']),
            'status_code' => 200,
            'is_success' => true,
        ]);

        // Unfiltered logs request shows both logs
        $response = $this->actingAs($admin)->get(route('admin.llm-providers.logs', $provider));
        $response->assertStatus(200);
        $response->assertSee('HTTP Inspection #' . $log1->id);
        $response->assertSee('HTTP Inspection #' . $log2->id);
        $response->assertSee('json-tree-container');

        // Filtered logs request for session 1 only shows log1
        $filteredResponse = $this->actingAs($admin)->get(route('admin.llm-providers.logs', [
            'llm_provider' => $provider,
            'chat_session_id' => $session1->id,
        ]));
        $filteredResponse->assertStatus(200);
        $filteredResponse->assertSee('HTTP Inspection #' . $log1->id);
        $filteredResponse->assertDontSee('HTTP Inspection #' . $log2->id);
        $filteredResponse->assertSee('Filtering by Session #' . $session1->id);
    }
}
