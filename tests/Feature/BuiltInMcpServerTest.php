<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\McpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BuiltInMcpServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_mcp_api_returns_initialize_response()
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('result.serverInfo.name', 'Laravel Built-in MCP Server');
    }

    public function test_mcp_api_returns_tools_list()
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ]);

        $response->assertStatus(200);
        $tools = $response->json('result.tools');
        $this->assertIsArray($tools);

        $toolNames = array_column($tools, 'name');
        $this->assertContains('web_fetch', $toolNames);
        $this->assertContains('web_search', $toolNames);
        $this->assertContains('internal_company_directory', $toolNames);
        $this->assertContains('execute_python_script', $toolNames);
    }

    public function test_mcp_api_executes_web_fetch_tool()
    {
        Http::fake([
            'https://example.com/test-article' => Http::response('<html><head><title>Test Article Title</title></head><body><h1>Heading</h1><p>This is paragraph text.</p><script>alert("bad")</script></body></html>', 200),
        ]);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'web_fetch',
                'arguments' => [
                    'url' => 'https://example.com/test-article',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $content = $response->json('result.content');
        $this->assertStringContainsString('Test Article Title', $content);
        $this->assertStringContainsString('This is paragraph text.', $content);
        $this->assertStringNotContainsString('alert("bad")', $content);
    }

    public function test_mcp_api_executes_internal_company_directory_tool()
    {
        User::create([
            'name' => 'Robert HR Specialist',
            'email' => 'robert.hr@company.com',
            'password' => bcrypt('password'),
            'role' => 'regular',
        ]);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'internal_company_directory',
                'arguments' => [
                    'query' => 'Robert',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $content = $response->json('result.content');
        $this->assertStringContainsString('Robert HR Specialist', $content);
        $this->assertStringContainsString('robert.hr@company.com', $content);
    }

    public function test_mcp_service_executes_builtin_web_fetch()
    {
        Http::fake([
            'https://docs.laravel.com/intro' => Http::response('<html><head><title>Laravel Docs</title></head><body><p>Welcome to Laravel documentation.</p></body></html>', 200),
        ]);

        $mcpService = app(McpService::class);
        $result = $mcpService->executeTool('web_fetch', ['url' => 'https://docs.laravel.com/intro']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Laravel Docs', $result['output']);
        $this->assertStringContainsString('Welcome to Laravel documentation.', $result['output']);
    }

    public function test_mcp_api_executes_python_script_math_and_data()
    {
        $pythonScript = <<<'PY'
numbers = [10, 20, 30, 40, 50]
mean = sum(numbers) / len(numbers)
text = "Company Sales Growth"
print(f"Mean: {mean}, Upper: {text.upper()}")
PY;

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'execute_python_script',
                'arguments' => [
                    'script' => $pythonScript,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $content = $response->json('result.content');
        $this->assertStringContainsString('Mean: 30.0', $content);
        $this->assertStringContainsString('Upper: COMPANY SALES GROWTH', $content);
    }

    public function test_mcp_api_handles_python_syntax_error_gracefully()
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => [
                'name' => 'execute_python_script',
                'arguments' => [
                    'script' => 'def invalid_syntax(:',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $content = $response->json('result.content');
        $this->assertStringContainsString('SyntaxError', $content);
    }
}
