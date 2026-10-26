<?php

namespace Tests\Feature;

use App\Models\McpServer;
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
}
