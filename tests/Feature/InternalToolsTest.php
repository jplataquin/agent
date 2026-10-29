<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\McpServer;
use App\Models\User;
use App\Services\McpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalToolsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->user = User::where('role', User::ROLE_REGULAR)->first();
    }

    public function test_admin_can_access_internal_tools_tester_ui(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.internal-tools.index'));

        $response->assertOk();
        $response->assertSee('Internal Tools Tester', false);
        $response->assertSee('Select Internal Tool', false);
        $response->assertSee('web_fetch', false);
        $response->assertSee('web_search', false);
        $response->assertSee('internal_company_directory', false);
        $response->assertSee('execute_python_script', false);
    }

    public function test_backward_compatible_built_in_tools_route_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.built-in-tools.index'));

        $response->assertOk();
        $response->assertSee('Internal Tools Tester', false);
    }

    public function test_admin_can_execute_internal_tool_manually(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.internal-tools.test'), [
            'tool_name' => 'internal_company_directory',
            'arguments' => ['query' => 'engineering'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('tool', 'internal_company_directory');
        $response->assertJsonPath('result.success', true);
        $this->assertNotEmpty($response->json('result.output'));
    }

    public function test_active_tools_breakdown_differentiates_internal_and_external_tools(): void
    {
        $mcpService = app(McpService::class);
        $breakdown = $mcpService->getActiveToolsBreakdown();

        $this->assertArrayHasKey('internal', $breakdown);
        $this->assertArrayHasKey('external_mcp', $breakdown);
        $this->assertArrayHasKey('total', $breakdown);

        // Native internal tools should be counted
        $this->assertGreaterThanOrEqual(4, $breakdown['internal']);

        $initialExternal = $breakdown['external_mcp'];

        // Register a distinct external MCP server with mock tool cache
        McpServer::create([
            'name' => 'External Weather MCP',
            'endpoint_url' => 'https://external-weather.example.com/mcp',
            'auth_token' => 'ext_token_123',
            'description' => 'External weather data provider',
            'tools_cache' => [
                ['name' => 'get_forecast', 'description' => 'Forecast tool'],
                ['name' => 'get_radar', 'description' => 'Radar tool'],
            ],
            'is_active' => true,
        ]);

        $updatedBreakdown = $mcpService->getActiveToolsBreakdown();
        $this->assertEquals($breakdown['internal'], $updatedBreakdown['internal']);
        $this->assertEquals($initialExternal + 2, $updatedBreakdown['external_mcp']);
        $this->assertEquals($breakdown['total'] + 2, $updatedBreakdown['total']);
    }

    public function test_chat_window_displays_differentiated_internal_tools_status(): void
    {
        $session = ChatSession::create([
            'user_id' => $this->user->id,
            'title' => 'Tool Terminology Chat',
        ]);

        $response = $this->actingAs($this->user)->get("/chat/{$session->id}");

        $response->assertOk();
        // UI should say "Internal Tool" instead of "MCP Tool" when only internal tools are active
        $response->assertSee('Internal', false);
        $response->assertSee('Active', false);
    }
}
