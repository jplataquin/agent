<?php

namespace App\Services;

use App\Models\McpServer;
use Exception;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class McpDiagnosticsService
{
    public function __construct(
        protected BuiltInToolsService $builtInToolsService,
        protected McpService $mcpService
    ) {}

    /**
     * Run complete diagnostics audit across all built-in and external MCP servers and dependencies.
     */
    public function auditAll(): array
    {
        $system = $this->auditSystemEnvironment();
        $builtin = $this->auditBuiltinMcp();
        $python = $this->auditPythonSandbox();
        $playwright = $this->auditPlaywrightBrowser();
        $servers = $this->auditRegisteredServers();

        $subsystems = [
            'builtin_mcp' => $builtin,
            'python_sandbox' => $python,
            'playwright_browser' => $playwright,
            'registered_servers' => $servers,
        ];

        // Aggregate health metrics
        $healthyCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        foreach ($subsystems as $key => $sub) {
            $status = $sub['status'] ?? 'ok';
            if ($status === 'ok') {
                $healthyCount++;
            } elseif ($status === 'warning') {
                $warningCount++;
            } else {
                $errorCount++;
            }
        }

        $overallStatus = 'healthy';
        if ($errorCount > 0) {
            $overallStatus = 'critical';
        } elseif ($warningCount > 0) {
            $overallStatus = 'degraded';
        }

        return [
            'summary' => [
                'overall_status' => $overallStatus,
                'total_subsystems' => count($subsystems),
                'healthy_count' => $healthyCount,
                'warning_count' => $warningCount,
                'error_count' => $errorCount,
                'audited_at' => now()->toIso8601String(),
                'audited_at_formatted' => now()->format('M d, Y H:i:s T'),
            ],
            'system_environment' => $system,
            'subsystems' => $subsystems,
        ];
    }

    /**
     * Audit basic host system environment.
     */
    public function auditSystemEnvironment(): array
    {
        $tempDir = storage_path('app/temp_scripts');
        $tempDirWritable = is_dir($tempDir) && is_writable($tempDir);

        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
            $tempDirWritable = is_dir($tempDir) && is_writable($tempDir);
        }

        return [
            'php_version' => PHP_VERSION,
            'php_os' => PHP_OS,
            'php_sapi' => PHP_SAPI,
            'current_user' => get_current_user() ?: (exec('whoami') ?: 'unknown'),
            'temp_scripts_dir' => $tempDir,
            'temp_dir_writable' => $tempDirWritable,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];
    }

    /**
     * Audit Native Built-in Web & System MCP Tools.
     */
    public function auditBuiltinMcp(): array
    {
        $checks = [];
        $hasErrors = false;
        $hasWarnings = false;

        // 1. PHP cURL Extension
        $curlLoaded = extension_loaded('curl');
        $checks['php_curl'] = [
            'name' => 'PHP cURL Extension',
            'status' => $curlLoaded ? 'ok' : 'error',
            'message' => $curlLoaded ? 'Extension loaded' : 'Missing ext-curl (required for web_fetch and HTTP MCP)',
        ];
        if (! $curlLoaded) {
            $hasErrors = true;
        }

        // 2. PHP JSON Extension
        $jsonLoaded = extension_loaded('json');
        $checks['php_json'] = [
            'name' => 'PHP JSON Extension',
            'status' => $jsonLoaded ? 'ok' : 'error',
            'message' => $jsonLoaded ? 'Extension loaded' : 'Missing ext-json',
        ];
        if (! $jsonLoaded) {
            $hasErrors = true;
        }

        // 3. Temporary Script Directory
        $tempDir = storage_path('app/temp_scripts');
        $tempOk = is_dir($tempDir) && is_writable($tempDir);
        $checks['temp_directory'] = [
            'name' => 'Script Execution Sandbox Dir',
            'status' => $tempOk ? 'ok' : 'warning',
            'message' => $tempOk ? 'Writable ('.$tempDir.')' : 'Directory missing or not writable by web user',
        ];
        if (! $tempOk) {
            $hasWarnings = true;
        }

        // 4. Tools Declaration
        $tools = $this->builtInToolsService->getToolsDeclaration();
        $toolNames = array_column($tools, 'name');
        $checks['tools_declared'] = [
            'name' => 'Built-in Tools Declaration',
            'status' => count($tools) >= 4 ? 'ok' : 'warning',
            'message' => count($tools).' tools declared: '.implode(', ', $toolNames),
            'count' => count($tools),
            'tools' => $toolNames,
        ];

        // 5. Database Entry for Built-in MCP Server
        $dbServer = McpServer::where(function ($q) {
            $q->where('endpoint_url', 'like', '%/api/mcp%')
                ->orWhere('name', 'like', '%Built-in%');
        })->first();

        $checks['database_registration'] = [
            'name' => 'Database Registration',
            'status' => ($dbServer && $dbServer->is_active) ? 'ok' : 'warning',
            'message' => $dbServer ? "Registered (ID: {$dbServer->id}, Active: ".($dbServer->is_active ? 'Yes' : 'No').')' : 'Not yet registered in mcp_servers table',
            'server_id' => $dbServer?->id,
            'is_active' => $dbServer?->is_active ?? false,
        ];
        if (! $dbServer || ! $dbServer->is_active) {
            $hasWarnings = true;
        }

        return [
            'name' => 'Built-in Web & System MCP',
            'endpoint' => url('/api/mcp'),
            'status' => $hasErrors ? 'error' : ($hasWarnings ? 'warning' : 'ok'),
            'checks' => $checks,
            'tools_count' => count($tools),
            'tools' => $tools,
        ];
    }

    /**
     * Audit Python Sandbox Execution (Docker vs Host Python fallback).
     */
    public function auditPythonSandbox(): array
    {
        $checks = [];
        $hasErrors = false;
        $hasWarnings = false;

        $rawImage = (string) (config('services.python_sandbox.docker_image') ?: env('PYTHON_DOCKER_IMAGE') ?: 'python:3.15-slim');
        if (preg_match('/^python\.(\d)/', $rawImage)) {
            $rawImage = (string) preg_replace('/^python\./', 'python:', $rawImage);
        }

        // 1. Check Docker CLI
        $dockerPath = $this->findBinary('docker');
        $checks['docker_cli'] = [
            'name' => 'Docker CLI Binary',
            'status' => $dockerPath ? 'ok' : 'warning',
            'message' => $dockerPath ? "Found at {$dockerPath}" : 'Docker CLI not found in system PATH',
            'path' => $dockerPath,
        ];

        // 2. Check Docker Daemon & Socket
        $daemonOk = false;
        $daemonVersion = null;
        if ($dockerPath) {
            try {
                $process = new Process(['docker', 'info', '--format', '{{.ServerVersion}}']);
                $process->setTimeout(4);
                $process->run();
                if ($process->isSuccessful()) {
                    $daemonOk = true;
                    $daemonVersion = trim($process->getOutput()) ?: 'Active';
                    $checks['docker_daemon'] = [
                        'name' => 'Docker Daemon & Socket',
                        'status' => 'ok',
                        'message' => "Active (Version: {$daemonVersion})",
                    ];
                } else {
                    $err = trim($process->getErrorOutput() ?: $process->getOutput());
                    $checks['docker_daemon'] = [
                        'name' => 'Docker Daemon & Socket',
                        'status' => 'warning',
                        'message' => str_contains(strtolower($err), 'permission denied')
                            ? 'Permission denied accessing /var/run/docker.sock'
                            : 'Docker daemon is not running or unreachable: '.substr($err, 0, 100),
                    ];
                    $hasWarnings = true;
                }
            } catch (Exception $e) {
                $checks['docker_daemon'] = [
                    'name' => 'Docker Daemon & Socket',
                    'status' => 'warning',
                    'message' => 'Check failed: '.$e->getMessage(),
                ];
                $hasWarnings = true;
            }
        } else {
            $checks['docker_daemon'] = [
                'name' => 'Docker Daemon & Socket',
                'status' => 'warning',
                'message' => 'Skipped (Docker CLI not installed)',
            ];
            $hasWarnings = true;
        }

        // 3. Check Docker Image
        $imagePresent = false;
        if ($daemonOk) {
            try {
                $imgCheck = new Process(['docker', 'image', 'inspect', $rawImage]);
                $imgCheck->setTimeout(4);
                $imgCheck->run();
                if ($imgCheck->isSuccessful()) {
                    $imagePresent = true;
                    $checks['docker_image'] = [
                        'name' => "Docker Image ({$rawImage})",
                        'status' => 'ok',
                        'message' => 'Image is pulled and available locally',
                    ];
                } else {
                    $checks['docker_image'] = [
                        'name' => "Docker Image ({$rawImage})",
                        'status' => 'warning',
                        'message' => "Image '{$rawImage}' not found locally. Run: docker pull {$rawImage}",
                    ];
                    $hasWarnings = true;
                }
            } catch (Exception $e) {
                $checks['docker_image'] = [
                    'name' => "Docker Image ({$rawImage})",
                    'status' => 'warning',
                    'message' => 'Failed to inspect image: '.$e->getMessage(),
                ];
                $hasWarnings = true;
            }
        }

        // 4. Check Local Python3 Fallback
        $pythonPath = $this->findBinary('python3') ?: $this->findBinary('python');
        $pythonVersion = null;
        if ($pythonPath) {
            try {
                $pyProc = new Process([$pythonPath, '--version']);
                $pyProc->setTimeout(3);
                $pyProc->run();
                $pythonVersion = trim($pyProc->getOutput() ?: $pyProc->getErrorOutput());
            } catch (Exception $e) {
                $pythonVersion = 'Unknown';
            }
        }

        $checks['host_python'] = [
            'name' => 'Host Python Runtime',
            'status' => $pythonPath ? 'ok' : 'warning',
            'message' => $pythonPath ? "Found at {$pythonPath} ({$pythonVersion})" : 'Host python3 binary not found',
            'path' => $pythonPath,
            'version' => $pythonVersion,
        ];

        // Determine Active Sandbox Mode
        $activeMode = 'Unavailable';
        if ($daemonOk && $imagePresent) {
            $activeMode = 'Docker Container Sandbox (Isolated)';
        } elseif ($pythonPath) {
            $activeMode = 'Host Python3 (Fallback Mode)';
        }

        // 5. Functional Self-Test Execution
        $testResult = null;
        $testSuccess = false;
        $testDuration = 0;
        try {
            $start = microtime(true);
            $testExec = $this->builtInToolsService->execute('execute_python_script', [
                'script' => 'print(21 * 2)',
            ]);
            $testDuration = round((microtime(true) - $start) * 1000, 1);
            if ($testExec['success'] && trim((string) $testExec['output']) === '42') {
                $testSuccess = true;
                $testResult = "Calculated 21 * 2 = 42 successfully in {$testDuration}ms";
            } else {
                $testResult = 'Execution returned unexpected output: '.substr((string) ($testExec['output'] ?? ''), 0, 150);
            }
        } catch (Exception $e) {
            $testResult = 'Execution error: '.$e->getMessage();
        }

        $checks['execution_test'] = [
            'name' => 'Self-Test Execution',
            'status' => $testSuccess ? 'ok' : ($activeMode === 'Unavailable' ? 'error' : 'warning'),
            'message' => $testResult,
            'duration_ms' => $testDuration,
            'success' => $testSuccess,
        ];

        if (! $testSuccess) {
            if ($activeMode === 'Unavailable') {
                $hasErrors = true;
            } else {
                $hasWarnings = true;
            }
        }

        return [
            'name' => 'Python Code Execution Sandbox',
            'active_mode' => $activeMode,
            'status' => $hasErrors ? 'error' : ($hasWarnings ? 'warning' : 'ok'),
            'checks' => $checks,
            'docker_image' => $rawImage,
            'remediation' => [
                'install_docker' => 'sudo apt update && sudo apt install -y docker.io && sudo usermod -aG docker www-data',
                'pull_image' => "sudo docker pull {$rawImage}",
                'install_python' => 'sudo apt update && sudo apt install -y python3',
            ],
        ];
    }

    /**
     * Audit Playwright Headless Browser MCP Server & Dependencies.
     */
    public function auditPlaywrightBrowser(): array
    {
        $checks = [];
        $hasErrors = false;
        $hasWarnings = false;

        // 1. Node.js Runtime
        $nodePath = $this->findBinary('node');
        $nodeVersion = null;
        if ($nodePath) {
            try {
                $nProc = new Process([$nodePath, '-v']);
                $nProc->setTimeout(3);
                $nProc->run();
                $nodeVersion = trim($nProc->getOutput());
            } catch (Exception $e) {
                $nodeVersion = 'Unknown';
            }
        }

        $checks['node_runtime'] = [
            'name' => 'Node.js Runtime',
            'status' => $nodePath ? 'ok' : 'error',
            'message' => $nodePath ? "Found at {$nodePath} ({$nodeVersion})" : 'Node.js binary not found in system PATH',
            'path' => $nodePath,
            'version' => $nodeVersion,
        ];
        if (! $nodePath) {
            $hasErrors = true;
        }

        // 2. npm Packages (express and playwright)
        $pkgPath = base_path('package.json');
        $hasPackageJson = file_exists($pkgPath);
        $expressVersion = null;
        $playwrightVersion = null;

        $expressPkg = base_path('node_modules/express/package.json');
        if (file_exists($expressPkg)) {
            $data = json_decode((string) file_get_contents($expressPkg), true);
            $expressVersion = $data['version'] ?? 'installed';
        }

        $playwrightPkg = base_path('node_modules/playwright/package.json');
        if (file_exists($playwrightPkg)) {
            $data = json_decode((string) file_get_contents($playwrightPkg), true);
            $playwrightVersion = $data['version'] ?? 'installed';
        }

        $checks['express_package'] = [
            'name' => 'Express Dependency (npm)',
            'status' => $expressVersion ? 'ok' : 'warning',
            'message' => $expressVersion ? "Installed (v{$expressVersion})" : 'Missing in node_modules (Run: npm install express)',
            'version' => $expressVersion,
        ];
        if (! $expressVersion) {
            $hasWarnings = true;
        }

        $checks['playwright_package'] = [
            'name' => 'Playwright Library (npm)',
            'status' => $playwrightVersion ? 'ok' : 'warning',
            'message' => $playwrightVersion ? "Installed (v{$playwrightVersion})" : 'Missing in node_modules (Run: npm install playwright)',
            'version' => $playwrightVersion,
        ];
        if (! $playwrightVersion) {
            $hasWarnings = true;
        }

        // 3. Playwright Chromium Browser Binary
        $chromiumFound = false;
        $chromiumPath = null;
        $candidatePaths = array_filter([
            env('PLAYWRIGHT_BROWSERS_PATH'),
            (getenv('HOME') ?: '/root').'/.cache/ms-playwright',
            '/var/www/.cache/ms-playwright',
            base_path('node_modules/playwright/.local-browsers'),
        ]);

        foreach ($candidatePaths as $dir) {
            if (is_dir($dir)) {
                $files = @scandir($dir) ?: [];
                foreach ($files as $file) {
                    if (str_starts_with($file, 'chromium-') || str_starts_with($file, 'chromium_headless_shell-')) {
                        $chromiumFound = true;
                        $chromiumPath = "{$dir}/{$file}";
                        break 2;
                    }
                }
            }
        }

        $checks['chromium_binary'] = [
            'name' => 'Headless Chromium Binary',
            'status' => $chromiumFound ? 'ok' : 'warning',
            'message' => $chromiumFound ? "Installed at {$chromiumPath}" : 'Chromium binary not found in standard cache locations (Run: npx playwright install chromium)',
            'path' => $chromiumPath,
        ];
        if (! $chromiumFound) {
            $hasWarnings = true;
        }

        // 4. Live Server Daemon Health Check
        $serverPort = 3030;
        $serverHost = 'http://127.0.0.1';

        // Check if defined in database
        $dbServer = McpServer::where('name', 'like', '%Playwright%')
            ->orWhere('endpoint_url', 'like', '%:3030%')
            ->first();

        if ($dbServer && ! empty($dbServer->endpoint_url)) {
            $parsed = parse_url($dbServer->endpoint_url);
            if (! empty($parsed['port'])) {
                $serverPort = (int) $parsed['port'];
            }
            if (! empty($parsed['host'])) {
                $serverHost = ($parsed['scheme'] ?? 'http').'://'.$parsed['host'];
            }
        }

        $healthEndpoint = "{$serverHost}:{$serverPort}/health";
        $serverReachable = false;
        $serverData = null;
        $serverLatencyMs = 0;

        try {
            $start = microtime(true);
            $resp = Http::timeout(2)->get($healthEndpoint);
            $serverLatencyMs = round((microtime(true) - $start) * 1000, 1);
            if ($resp->successful()) {
                $serverReachable = true;
                $serverData = $resp->json();
            }
        } catch (Exception $e) {
            $serverReachable = false;
        }

        $checks['server_daemon'] = [
            'name' => 'Playwright Server Daemon',
            'status' => $serverReachable ? 'ok' : 'warning',
            'message' => $serverReachable
                ? "Active on {$healthEndpoint} ({$serverLatencyMs}ms latency, v".($serverData['version'] ?? '1.0.0').')'
                : "Offline / Unreachable on {$healthEndpoint} (Start via systemd: sudo systemctl start agent-browser.service)",
            'endpoint' => $healthEndpoint,
            'latency_ms' => $serverLatencyMs,
            'details' => $serverData,
        ];
        if (! $serverReachable) {
            $hasWarnings = true;
        }

        // 5. Database Registration
        $checks['database_registration'] = [
            'name' => 'Database Registration',
            'status' => ($dbServer && $dbServer->is_active) ? 'ok' : 'warning',
            'message' => $dbServer
                ? "Registered (ID: {$dbServer->id}, Active: ".($dbServer->is_active ? 'Yes' : 'No').', Tools: '.(is_array($dbServer->tools_cache) ? count($dbServer->tools_cache) : 0).')'
                : 'Server not registered in mcp_servers table (Run: php artisan mcp:register-playwright --sync)',
            'server_id' => $dbServer?->id,
            'is_active' => $dbServer?->is_active ?? false,
        ];
        if (! $dbServer || ! $dbServer->is_active) {
            $hasWarnings = true;
        }

        return [
            'name' => 'Playwright Headless Browser MCP',
            'endpoint' => "{$serverHost}:{$serverPort}/mcp",
            'health_endpoint' => $healthEndpoint,
            'status' => $hasErrors ? 'error' : ($hasWarnings ? 'warning' : 'ok'),
            'checks' => $checks,
            'server_reachable' => $serverReachable,
            'server_data' => $serverData,
            'remediation' => [
                'install_deps' => 'sudo npx playwright install-deps chromium',
                'install_browser' => 'PLAYWRIGHT_BROWSERS_PATH=0 npx playwright install chromium',
                'register_command' => 'php artisan mcp:register-playwright --sync',
                'systemd_service' => 'sudo systemctl restart agent-browser.service',
            ],
        ];
    }

    /**
     * Audit all registered MCP servers in the database.
     */
    public function auditRegisteredServers(): array
    {
        $servers = McpServer::orderBy('name')->get();
        $auditList = [];
        $hasErrors = false;
        $hasWarnings = false;

        foreach ($servers as $server) {
            $latencyMs = 0;
            $reachable = false;
            $errorMessage = null;
            $liveToolsCount = null;

            // Ping server endpoint
            try {
                $start = microtime(true);
                $resp = Http::withoutVerifying()
                    ->timeout(4)
                    ->post($server->endpoint_url, [
                        'jsonrpc' => '2.0',
                        'id' => 1,
                        'method' => 'tools/list',
                        'params' => (object) [],
                    ]);
                $latencyMs = round((microtime(true) - $start) * 1000, 1);

                if ($resp->successful()) {
                    $json = $resp->json();
                    if (isset($json['result']['tools']) && is_array($json['result']['tools'])) {
                        $reachable = true;
                        $liveToolsCount = count($json['result']['tools']);
                    } elseif (isset($json['error'])) {
                        $errorMessage = 'JSON-RPC Error: '.($json['error']['message'] ?? 'Unknown');
                    } else {
                        $reachable = true;
                    }
                } else {
                    $errorMessage = 'HTTP '.$resp->status();
                }
            } catch (Exception $e) {
                $errorMessage = 'Connection failed: '.$e->getMessage();
            }

            $status = 'ok';
            if (! $server->is_active) {
                $status = 'warning';
            } elseif (! $reachable) {
                $status = 'error';
                $hasErrors = true;
            } elseif ($liveToolsCount !== null && is_array($server->tools_cache) && count($server->tools_cache) !== $liveToolsCount) {
                $status = 'warning';
                $hasWarnings = true;
            }

            $auditList[] = [
                'id' => $server->id,
                'name' => $server->name,
                'endpoint_url' => $server->endpoint_url,
                'auth_type' => $server->auth_type,
                'is_active' => $server->is_active,
                'cached_tools_count' => is_array($server->tools_cache) ? count($server->tools_cache) : 0,
                'live_tools_count' => $liveToolsCount,
                'reachable' => $reachable,
                'latency_ms' => $latencyMs,
                'error_message' => $errorMessage,
                'last_sync_at' => $server->last_sync_at?->diffForHumans(),
                'last_sync_status' => $server->last_sync_status,
                'status' => $status,
            ];
        }

        return [
            'name' => 'Registered MCP Servers',
            'status' => $hasErrors ? 'error' : ($hasWarnings ? 'warning' : 'ok'),
            'total_servers' => count($servers),
            'servers' => $auditList,
        ];
    }

    /**
     * Find binary in system PATH safely.
     */
    protected function findBinary(string $binary): ?string
    {
        try {
            $process = new Process(['which', $binary]);
            $process->setTimeout(2);
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                if (! empty($path) && file_exists($path)) {
                    return $path;
                }
            }
        } catch (Exception $e) {
            // Ignore
        }

        // Common fallback locations
        $commonLocations = [
            "/usr/bin/{$binary}",
            "/usr/local/bin/{$binary}",
            "/bin/{$binary}",
            "/snap/bin/{$binary}",
        ];

        foreach ($commonLocations as $loc) {
            if (file_exists($loc) && is_executable($loc)) {
                return $loc;
            }
        }

        return null;
    }
}
