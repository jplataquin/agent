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
        parent::setUp();
        $this->seed();
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
}
