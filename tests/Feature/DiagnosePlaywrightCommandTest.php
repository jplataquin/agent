<?php

namespace Tests\Feature;

use App\Models\McpServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosePlaywrightCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_playwright_diagnose_command_runs_diagnostics()
    {
        $this->artisan('playwright:diagnose')
            ->expectsOutputToContain('Playwright Headless Browser & Crashpad Diagnostic')
            ->expectsOutputToContain('Check Component')
            ->expectsOutputToContain('Critical Paths & Permissions Audit');
    }

    public function test_playwright_doctor_alias_runs_same_command()
    {
        $this->artisan('playwright:doctor')
            ->expectsOutputToContain('Playwright Headless Browser & Crashpad Diagnostic')
            ->expectsOutputToContain('Critical Paths & Permissions Audit');
    }

    public function test_playwright_diagnose_json_option_outputs_structured_json()
    {
        \Illuminate\Support\Facades\Artisan::call('playwright:diagnose', ['--json' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('environment', $data);
        $this->assertArrayHasKey('paths', $data);
        $this->assertArrayHasKey('node_runtime', $data);
        $this->assertArrayHasKey('packages', $data);
        $this->assertArrayHasKey('browser', $data);
        $this->assertArrayHasKey('bridge_config', $data);
        $this->assertArrayHasKey('systemd', $data);
        $this->assertArrayHasKey('daemon', $data);
        $this->assertArrayHasKey('database', $data);
        $this->assertArrayHasKey('crashpad_issues', $data);
        $this->assertArrayHasKey('remediations', $data);

        // Verify bridge_config flags
        $this->assertTrue($data['bridge_config']['has_disable_crashpad_flag']);
        $this->assertTrue($data['bridge_config']['has_crash_dumps_dir_flag']);
    }

    public function test_playwright_diagnose_fix_flag_creates_runtime_directories()
    {
        $this->artisan('playwright:diagnose', ['--fix' => true])
            ->expectsOutputToContain('Automated Fixes Applied');

        $runtimeDir = env('PLAYWRIGHT_RUNTIME_DIR') ?: '/tmp/playwright-agent-runtime';
        $this->assertDirectoryExists($runtimeDir);
        $this->assertDirectoryExists("{$runtimeDir}/.config");
        $this->assertDirectoryExists("{$runtimeDir}/.cache");
        $this->assertDirectoryExists("{$runtimeDir}/crash_dumps");
        $this->assertDirectoryExists("{$runtimeDir}/user-data");
    }

    public function test_playwright_diagnose_detects_registered_database_server()
    {
        McpServer::create([
            'name' => 'Playwright Headless Browser MCP',
            'endpoint_url' => 'http://127.0.0.1:3030/mcp',
            'auth_type' => 'none',
            'is_active' => true,
            'tools_cache' => [['name' => 'browser_navigate']],
        ]);

        \Illuminate\Support\Facades\Artisan::call('playwright:diagnose', ['--json' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $data = json_decode($output, true);

        $this->assertTrue($data['database']['registered']);
        $this->assertTrue($data['database']['is_active']);
        $this->assertEquals(1, $data['database']['tools_count']);
    }

    public function test_playwright_diagnose_defaults_to_agent_prod_browser_service()
    {
        \Illuminate\Support\Facades\Artisan::call('playwright:diagnose', ['--json' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $data = json_decode($output, true);

        $this->assertEquals('agent-prod-browser', $data['systemd']['service_name']);
        $this->assertEquals('/etc/systemd/system/agent-prod-browser.service', $data['systemd']['service_file']);
    }

    public function test_playwright_diagnose_accepts_custom_service_name_and_strips_extension()
    {
        \Illuminate\Support\Facades\Artisan::call('playwright:diagnose', [
            '--service' => 'agent-prod-browser.service',
            '--json' => true,
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $data = json_decode($output, true);

        $this->assertEquals('agent-prod-browser', $data['systemd']['service_name']);
        $this->assertEquals('/etc/systemd/system/agent-prod-browser.service', $data['systemd']['service_file']);
    }
}
