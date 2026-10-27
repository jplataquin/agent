<?php

namespace Tests\Feature;

use App\Models\McpServer;
use App\Models\User;
use App\Services\McpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_sync_tools_parses_json_rpc_tools_list()
    {
        $server = McpServer::create([
            'name' => 'Internal HR MCP',
            'endpoint_url' => 'https://mcp.internal/hr',
            'auth_type' => 'bearer',
            'auth_token' => 'sample_token',
            'is_active' => true,
        ]);

        Http::fake([
            'https://mcp.internal/hr' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'tools' => [
                        [
                            'name' => 'get_employee_leave_balance',
                            'description' => 'Retrieve available vacation days for an employee',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'employee_id' => ['type' => 'string'],
                                ],
                                'required' => ['employee_id'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->syncTools($server);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);

        $server->refresh();
        $this->assertCount(1, $server->tools_cache);
        $this->assertEquals('get_employee_leave_balance', $server->tools_cache[0]['name']);
        $this->assertEquals('success', $server->last_sync_status);
        $this->assertNull($server->last_sync_error);
    }

    public function test_mcp_sync_tools_handles_initialize_handshake()
    {
        $server = McpServer::create([
            'name' => 'Stateful Python FastMCP Server',
            'endpoint_url' => 'https://mcp.internal/fastmcp',
            'auth_type' => 'none',
            'is_active' => true,
        ]);

        $callCount = 0;
        Http::fake([
            'https://mcp.internal/fastmcp' => function ($request) use (&$callCount) {
                $payload = json_decode($request->body(), true);
                $method = $payload['method'] ?? '';

                if ($method === 'tools/list' && $callCount === 0) {
                    $callCount++;

                    // First tools/list fails because server expects initialize
                    return Http::response([
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 1,
                        'error' => [
                            'code' => -32002,
                            'message' => 'Server not initialized',
                        ],
                    ], 200);
                }

                if ($method === 'initialize') {
                    return Http::response([
                        'jsonrpc' => '2.0',
                        'id' => $payload['id'] ?? 1,
                        'result' => [
                            'protocolVersion' => '2024-11-05',
                            'capabilities' => ['tools' => []],
                            'serverInfo' => ['name' => 'FastMCP', 'version' => '0.1.0'],
                        ],
                    ], 200);
                }

                if ($method === 'notifications/initialized') {
                    return Http::response('', 200);
                }

                // Subsequent tools/list succeeds
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => $payload['id'] ?? 2,
                    'result' => [
                        'tools' => [
                            [
                                'name' => 'query_database',
                                'description' => 'Executes read-only SQL queries',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => ['sql' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            },
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->syncTools($server);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals('query_database', $result['tools'][0]['name']);

        $server->refresh();
        $this->assertEquals('success', $server->last_sync_status);
        $this->assertCount(1, $server->tools_cache);
    }

    public function test_mcp_sync_tools_handles_json_rpc_error_and_records_last_sync_error()
    {
        $server = McpServer::create([
            'name' => 'Broken MCP Server',
            'endpoint_url' => 'https://mcp.internal/broken',
            'auth_type' => 'bearer',
            'auth_token' => 'invalid_token',
            'is_active' => true,
        ]);

        Http::fake([
            'https://mcp.internal/broken' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'error' => [
                    'code' => -32600,
                    'message' => 'Unauthorized invalid token',
                ],
            ], 200),
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->syncTools($server);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unauthorized invalid token', $result['error']);

        $server->refresh();
        $this->assertEquals('error', $server->last_sync_status);
        $this->assertStringContainsString('Unauthorized invalid token', $server->last_sync_error);
    }

    public function test_admin_mcp_servers_sync_endpoint_returns_json_for_ajax()
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@company.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $server = McpServer::create([
            'name' => 'Sample API',
            'endpoint_url' => 'https://api.internal/mcp',
            'auth_type' => 'none',
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.internal/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'tools' => [
                        [
                            'name' => 'fetch_analytics',
                            'description' => 'Get monthly active users',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson("/admin/mcp-servers/{$server->id}/sync");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 1,
        ]);
        $response->assertJsonStructure(['success', 'count', 'tools', 'message', 'last_sync_at']);
    }

    public function test_mcp_service_supports_custom_header_authentication()
    {
        $server = McpServer::create([
            'name' => 'Custom Header Server',
            'endpoint_url' => 'https://mcp.internal/custom-auth',
            'auth_type' => 'custom_header',
            'custom_header_name' => 'X-API-Key',
            'auth_token' => 'my-secret-key-12345',
            'is_active' => true,
        ]);

        $mcpService = app(McpService::class);
        $headers = $mcpService->getHeaders($server);

        $this->assertArrayHasKey('X-API-Key', $headers);
        $this->assertEquals('my-secret-key-12345', $headers['X-API-Key']);
    }

    public function test_mcp_execute_tool_dispatches_json_rpc_call()
    {
        $server = McpServer::create([
            'name' => 'Internal HR MCP',
            'endpoint_url' => 'https://mcp.internal/hr',
            'auth_type' => 'bearer',
            'auth_token' => 'sample_token',
            'is_active' => true,
            'tools_cache' => [
                [
                    'name' => 'get_employee_leave_balance',
                    'description' => 'Retrieve available vacation days for an employee',
                ],
            ],
        ]);

        Http::fake([
            'https://mcp.internal/hr' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'content' => 'Remaining PTO: 14 days',
                ],
            ], 200),
        ]);

        $mcpService = app(McpService::class);
        $execution = $mcpService->executeTool('get_employee_leave_balance', ['employee_id' => 'EMP-102']);

        $this->assertTrue($execution['success']);
        $this->assertEquals('Remaining PTO: 14 days', $execution['output']);
    }

    public function test_external_server_with_api_mcp_path_is_not_hijacked_as_builtin()
    {
        $server = McpServer::create([
            'name' => 'Remote CRM MCP',
            'endpoint_url' => 'https://crm.external-system.com/api/mcp',
            'auth_type' => 'bearer',
            'auth_token' => 'remote-token-999',
            'is_active' => true,
        ]);

        Http::fake([
            'https://crm.external-system.com/api/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'tools' => [
                        [
                            'name' => 'crm_lookup_deal',
                            'description' => 'Look up CRM deals by ID',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => ['deal_id' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->syncTools($server);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals('crm_lookup_deal', $result['tools'][0]['name']);

        // Must NOT have been hijacked into built-in web_fetch/web_search tools
        $server->refresh();
        $this->assertCount(1, $server->tools_cache);
        $this->assertEquals('crm_lookup_deal', $server->tools_cache[0]['name']);
    }

    public function test_mcp_sync_tools_parses_snake_case_input_schema_from_fastmcp()
    {
        $server = McpServer::create([
            'name' => 'FastMCP Python Server',
            'endpoint_url' => 'http://127.0.0.1:5000/mcp',
            'auth_type' => 'none',
            'is_active' => true,
        ]);

        Http::fake([
            'http://127.0.0.1:5000/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'tools' => [
                        [
                            'name' => 'python_math_eval',
                            'description' => 'Evaluates mathematical formulas',
                            'input_schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'expression' => ['type' => 'string'],
                                ],
                                'required' => ['expression'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->syncTools($server);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals('python_math_eval', $result['tools'][0]['name']);
        $this->assertArrayHasKey('expression', $result['tools'][0]['inputSchema']['properties']);
    }
}
