<?php

namespace Tests\Feature;

use App\Models\McpServer;
use App\Services\McpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaywrightMcpIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_command_registers_playwright_mcp_server()
    {
        $this->artisan('mcp:register-playwright', [
            '--port' => 3030,
            '--host' => 'http://localhost',
            '--name' => 'Playwright Headless Browser MCP',
        ])
            ->assertExitCode(0);

        $server = McpServer::where('name', 'Playwright Headless Browser MCP')->first();
        $this->assertNotNull($server);
        $this->assertEquals('http://localhost:3030/mcp', $server->endpoint_url);
        $this->assertEquals('none', $server->auth_type);
        $this->assertTrue($server->is_active);
        $this->assertIsArray($server->tools_cache);

        $toolNames = array_column($server->tools_cache, 'name');
        $this->assertContains('browser_navigate', $toolNames);
        $this->assertContains('browser_click', $toolNames);
        $this->assertContains('browser_type', $toolNames);
        $this->assertContains('browser_press_key', $toolNames);
        $this->assertContains('browser_snapshot', $toolNames);
        $this->assertContains('browser_evaluate', $toolNames);
        $this->assertContains('browser_reset', $toolNames);
    }

    public function test_artisan_command_syncs_with_server_endpoint()
    {
        Http::fake([
            'http://localhost:3030/health' => Http::response([
                'status' => 'ok',
                'server' => 'playwright-mcp-server',
            ], 200),
            'http://localhost:3030/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'tools' => [
                        [
                            'name' => 'browser_navigate',
                            'description' => 'Navigate to URL',
                            'inputSchema' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string']], 'required' => ['url']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('mcp:register-playwright', [
            '--port' => 3030,
            '--sync' => true,
        ])
            ->assertExitCode(0);

        $server = McpServer::where('name', 'Playwright Headless Browser MCP')->first();
        $this->assertNotNull($server);
        $this->assertEquals('success', $server->last_sync_status);
        $this->assertCount(1, $server->tools_cache);
    }

    public function test_mcp_service_can_execute_browser_tools_via_json_rpc()
    {
        $server = McpServer::create([
            'name' => 'Playwright Headless Browser MCP',
            'endpoint_url' => 'http://localhost:3030/mcp',
            'auth_type' => 'none',
            'is_active' => true,
            'tools_cache' => [
                [
                    'name' => 'browser_navigate',
                    'description' => 'Navigate to URL',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => ['url' => ['type' => 'string']],
                        'required' => ['url'],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'http://localhost:3030/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 123,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Page Title: Example Domain\nCurrent URL: https://example.com\n\nPage Content:\nExample Domain",
                        ],
                    ],
                ],
            ], 200),
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->executeTool('browser_navigate', ['url' => 'https://example.com']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Example Domain', $result['output']);
    }

    public function test_mcp_service_handles_browser_execution_error_gracefully()
    {
        McpServer::create([
            'name' => 'Playwright Headless Browser MCP',
            'endpoint_url' => 'http://localhost:3030/mcp',
            'auth_type' => 'none',
            'is_active' => true,
            'tools_cache' => [
                [
                    'name' => 'browser_click',
                    'description' => 'Click an element',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => ['selector' => ['type' => 'string']],
                        'required' => ['selector'],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'http://localhost:3030/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 123,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Playwright execution error: Element not found: #missing-btn',
                        ],
                    ],
                    'isError' => true,
                ],
            ], 200),
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->executeTool('browser_click', ['selector' => '#missing-btn']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Playwright execution error', $result['output']);
    }

    public function test_mcp_service_handles_official_playwright_accessibility_snapshot_responses()
    {
        McpServer::create([
            'name' => 'Playwright Headless Browser MCP',
            'endpoint_url' => 'http://localhost:3030/mcp',
            'auth_type' => 'none',
            'is_active' => true,
            'tools_cache' => [
                [
                    'name' => 'browser_snapshot',
                    'description' => 'Capture accessibility snapshot of current page',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
        ]);

        $yamlSnapshot = "### Page\n- Page URL: https://example.com/\n- Page Title: Example Domain\n### Snapshot\n```yaml\n- generic [active] [ref=e1]:\n  - link \"Learn more\" [ref=e2]\n```";

        Http::fake([
            'http://localhost:3030/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 456,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $yamlSnapshot,
                        ],
                    ],
                    'isError' => false,
                ],
            ], 200),
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->executeTool('browser_snapshot', []);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('ref=e2', $result['output']);
        $this->assertStringContainsString('Example Domain', $result['output']);
    }
}
