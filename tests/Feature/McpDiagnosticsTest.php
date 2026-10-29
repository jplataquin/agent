<?php

namespace Tests\Feature;

use App\Models\McpServer;
use App\Models\User;
use App\Services\McpDiagnosticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->user = User::factory()->create([
            'role' => 'user',
        ]);
    }

    public function test_guest_cannot_access_diagnostics(): void
    {
        $response = $this->get(route('admin.mcp-diagnostics.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_diagnostics(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.mcp-diagnostics.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_access_diagnostics_dashboard(): void
    {
        Http::fake([
            'http://127.0.0.1:3030/health' => Http::response([
                'status' => 'ok',
                'server' => 'playwright-mcp-server',
                'version' => '1.0.0',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.mcp-diagnostics.index'));

        $response->assertOk();
        $response->assertSee('MCP Diagnostics & Health Auditor', false);
        $response->assertSee('Internal Tools', false);
        $response->assertSee('Python Execution Sandbox', false);
        $response->assertSee('Playwright Browser MCP Server', false);
    }

    public function test_admin_can_run_diagnostics_via_ajax(): void
    {
        Http::fake([
            'http://127.0.0.1:3030/health' => Http::response([
                'status' => 'ok',
                'server' => 'playwright-mcp-server',
                'version' => '1.0.0',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.mcp-diagnostics.run'));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'audit' => [
                'summary' => [
                    'overall_status',
                    'total_subsystems',
                    'healthy_count',
                    'warning_count',
                    'error_count',
                    'audited_at',
                ],
                'system_environment' => [
                    'php_version',
                    'php_os',
                    'temp_dir_writable',
                ],
                'subsystems' => [
                    'builtin_mcp' => [
                        'name',
                        'status',
                        'checks',
                    ],
                    'python_sandbox' => [
                        'name',
                        'active_mode',
                        'status',
                        'checks',
                    ],
                    'playwright_browser' => [
                        'name',
                        'status',
                        'checks',
                    ],
                    'registered_servers' => [
                        'name',
                        'total_servers',
                        'servers',
                    ],
                ],
            ],
        ]);
    }

    public function test_admin_can_sync_server_via_quick_action(): void
    {
        $server = McpServer::create([
            'name' => 'Test External Server',
            'endpoint_url' => 'https://mcp.external.test/mcp',
            'auth_type' => 'none',
            'is_active' => true,
        ]);

        Http::fake([
            'https://mcp.external.test/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'tools' => [
                        [
                            'name' => 'sample_remote_tool',
                            'description' => 'A test remote tool',
                            'inputSchema' => ['type' => 'object'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.mcp-diagnostics.action'), [
            'action' => 'sync_server',
            'server_id' => $server->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'server_id' => $server->id,
        ]);

        $server->refresh();
        $this->assertEquals('success', $server->last_sync_status);
        $this->assertCount(1, $server->tools_cache);
    }

    public function test_diagnostics_service_audits_subsystems_accurately(): void
    {
        $service = app(McpDiagnosticsService::class);
        $audit = $service->auditAll();

        $this->assertArrayHasKey('summary', $audit);
        $this->assertArrayHasKey('subsystems', $audit);
        $this->assertArrayHasKey('internal_tools', $audit['subsystems']);
        $this->assertArrayHasKey('builtin_mcp', $audit['subsystems']);
        $this->assertArrayHasKey('python_sandbox', $audit['subsystems']);
        $this->assertArrayHasKey('playwright_browser', $audit['subsystems']);
        $this->assertArrayHasKey('registered_servers', $audit['subsystems']);

        $builtinChecks = $audit['subsystems']['builtin_mcp']['checks'];
        $this->assertArrayHasKey('php_curl', $builtinChecks);
        $this->assertArrayHasKey('php_json', $builtinChecks);
        $this->assertArrayHasKey('tools_declared', $builtinChecks);

        $pythonChecks = $audit['subsystems']['python_sandbox']['checks'];
        $this->assertArrayHasKey('host_python', $pythonChecks);
        $this->assertArrayHasKey('execution_test', $pythonChecks);

        $browserChecks = $audit['subsystems']['playwright_browser']['checks'];
        $this->assertArrayHasKey('node_runtime', $browserChecks);
        $this->assertArrayHasKey('express_package', $browserChecks);
        $this->assertArrayHasKey('playwright_package', $browserChecks);
        $this->assertArrayHasKey('chromium_binary', $browserChecks);
    }
}
