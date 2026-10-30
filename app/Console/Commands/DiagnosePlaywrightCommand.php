<?php

namespace App\Console\Commands;

use App\Models\McpServer;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

#[Signature('playwright:diagnose {--test-launch : Attempt a live headless browser launch test} {--fix : Attempt to automatically create missing runtime directories and set safe permissions} {--service= : Systemd service name to inspect (default: auto-detected, e.g. agent-prod-browser or agent-browser)} {--json : Output report in JSON format for debugging}')]
#[Description('Diagnose Playwright, Chromium, and system environment issues (permissions, crashpad, dependencies, systemd)')]
class DiagnosePlaywrightCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'playwright:diagnose {--test-launch : Attempt a live headless browser launch test} {--fix : Attempt to automatically create missing runtime directories and set safe permissions} {--service= : Systemd service name to inspect (default: auto-detected, e.g. agent-prod-browser or agent-browser)} {--json : Output report in JSON format for debugging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose Playwright, Chromium, and system environment issues (permissions, crashpad, dependencies, systemd)';

    /**
     * Command aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = [
        'playwright:doctor',
        'mcp:playwright-diagnose',
    ];

    /**
     * Diagnostic report data collector.
     */
    protected array $report = [];

    /**
     * Flag indicating whether all vital checks passed.
     */
    protected bool $allPassed = true;

    /**
     * Critical crashpad issues detected.
     */
    protected array $crashpadIssues = [];

    /**
     * Actionable remediation commands.
     */
    protected array $remediations = [];

    /**
     * Actions taken when --fix is provided.
     */
    protected array $fixesApplied = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isJson = (bool) $this->option('json');
        $shouldFix = (bool) $this->option('fix');
        $serviceName = $this->resolveServiceName($this->option('service'));

        if (! $isJson) {
            $this->renderHeader();
        }

        // Apply automated fixes if requested before or during audit
        if ($shouldFix) {
            $this->applyAutomatedFixes();
        }

        // 1. Gather System Environment
        $systemInfo = $this->checkSystemEnvironment();

        // 2. Gather Environment Variables (HOME, XDG, PLAYWRIGHT)
        $envVars = $this->checkEnvironmentVariables($serviceName);

        // 3. Inspect Critical Paths & Permissions (Crashpad DB & Cache)
        $pathAudit = $this->checkCriticalDirectories();

        // 4. Inspect Node.js, npm, npx Runtimes
        $nodeInfo = $this->checkNodeRuntime();

        // 5. Inspect NPM Dependencies
        $npmPackages = $this->checkNpmPackages();

        // 6. Inspect Browser Binaries & Shared Libraries (ldd)
        $browserInfo = $this->checkBrowserBinaries();

        // 7. Inspect Systemd Service & Logs
        $systemdInfo = $this->checkSystemdService($serviceName);

        // 8. Inspect Playwright Bridge Script Config
        $bridgeInfo = $this->checkBridgeScript();

        // 9. Inspect Live Playwright Daemon & Port 3030
        $daemonInfo = $this->checkServerDaemon();

        // 10. Inspect Database Registration
        $dbInfo = $this->checkDatabaseRegistration();

        // 11. Live Launch Test (if requested)
        $launchTest = null;
        if ($this->option('test-launch')) {
            $launchTest = $this->runLiveLaunchTest();
        }

        // Consolidate Report
        $this->report = [
            'timestamp' => now()->toIso8601String(),
            'status' => $this->allPassed ? 'healthy' : (count($this->crashpadIssues) > 0 ? 'critical' : 'degraded'),
            'system' => $systemInfo,
            'environment' => $envVars,
            'paths' => $pathAudit,
            'node_runtime' => $nodeInfo,
            'packages' => $npmPackages,
            'browser' => $browserInfo,
            'bridge_config' => $bridgeInfo,
            'systemd' => $systemdInfo,
            'daemon' => $daemonInfo,
            'database' => $dbInfo,
            'launch_test' => $launchTest,
            'fixes_applied' => $this->fixesApplied,
            'crashpad_issues' => $this->crashpadIssues,
            'remediations' => array_values(array_unique($this->remediations)),
        ];

        if ($isJson) {
            $this->line(json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->allPassed ? self::SUCCESS : self::FAILURE;
        }

        // Render CLI Sections
        if ($shouldFix && count($this->fixesApplied) > 0) {
            $this->info('--- Automated Fixes Applied ---');
            foreach ($this->fixesApplied as $fix) {
                $this->line("  <info>✓</info> {$fix}");
            }
            $this->newLine();
        }

        $this->renderSummaryTable();
        $this->renderCrashpadDiagnosis();
        $this->renderRemediationGuide();

        $this->newLine();
        if ($this->allPassed) {
            $this->info('================================================================');
            $this->info(' [SUCCESS] Playwright and Chromium environment is healthy!');
            $this->info('================================================================');

            return self::SUCCESS;
        }

        if (count($this->crashpadIssues) > 0) {
            $this->error('================================================================');
            $this->error(' [CRITICAL] Potential Crashpad / Environment issues detected!');
            $this->error(' Review the suggested fixes above to resolve browser startup.');
            $this->error('================================================================');

            return self::FAILURE;
        }

        $this->warn('================================================================');
        $this->warn(' [WARNING] Some warnings were detected during diagnosis.');
        $this->warn('================================================================');

        return self::FAILURE;
    }

    /**
     * Render CLI Header.
     */
    protected function renderHeader(): void
    {
        $this->newLine();
        $this->info('================================================================');
        $this->info('       Playwright Headless Browser & Crashpad Diagnostic        ');
        $this->info('================================================================');
        $this->line(' Diagnosing Chromium runtime, Crashpad DB paths, systemd & permissions');
        $this->newLine();
    }

    /**
     * Apply automated fixes to create directories with permissive permissions.
     */
    protected function applyAutomatedFixes(): void
    {
        $runtimeDir = env('PLAYWRIGHT_RUNTIME_DIR') ?: '/tmp/playwright-agent-runtime';
        $subdirs = [
            $runtimeDir,
            "{$runtimeDir}/.config",
            "{$runtimeDir}/.cache",
            "{$runtimeDir}/crash_dumps",
            "{$runtimeDir}/user-data",
        ];

        foreach ($subdirs as $dir) {
            if (! is_dir($dir)) {
                if (@mkdir($dir, 0777, true)) {
                    @chmod($dir, 0777);
                    $this->fixesApplied[] = "Created directory: {$dir} (mode 0777)";
                }
            } else {
                @chmod($dir, 0777);
                $this->fixesApplied[] = "Set permissions on: {$dir} (mode 0777)";
            }
        }
    }

    /**
     * 1. Check OS and host environment details.
     */
    protected function checkSystemEnvironment(): array
    {
        $osRelease = $this->parseOsRelease();
        $currentUser = $this->getProcessUser();
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
        $gid = function_exists('posix_getegid') ? posix_getegid() : null;
        $isRoot = ($uid === 0 || $currentUser === 'root');

        // Check if www-data user exists
        $wwwDataExists = false;
        if (function_exists('posix_getpwnam')) {
            $wwwDataExists = (bool) posix_getpwnam('www-data');
        } else {
            $wwwDataExists = (bool) $this->executeShell('id -u www-data 2>/dev/null');
        }

        // Shared memory (/dev/shm) check
        $shmPath = '/dev/shm';
        $shmExists = is_dir($shmPath);
        $shmWritable = $shmExists && is_writable($shmPath);
        $shmFreeMb = $shmExists ? round(disk_free_space($shmPath) / (1024 * 1024), 1) : 0;
        $shmTotalMb = $shmExists ? round(disk_total_space($shmPath) / (1024 * 1024), 1) : 0;

        if ($shmExists && ! $shmWritable) {
            $this->crashpadIssues[] = '/dev/shm is not writable by current user. Chromium requires shared memory or --disable-dev-shm-usage.';
            $this->remediations[] = 'sudo chmod 1777 /dev/shm';
            $this->allPassed = false;
        }

        return [
            'os' => $osRelease['PRETTY_NAME'] ?? PHP_OS,
            'kernel' => php_uname('r'),
            'architecture' => php_uname('m'),
            'php_version' => PHP_VERSION,
            'current_user' => $currentUser,
            'uid' => $uid,
            'gid' => $gid,
            'is_root' => $isRoot,
            'www_data_exists' => $wwwDataExists,
            'dev_shm' => [
                'exists' => $shmExists,
                'writable' => $shmWritable,
                'total_mb' => $shmTotalMb,
                'free_mb' => $shmFreeMb,
            ],
            'is_container' => file_exists('/.dockerenv') || str_contains((string) @file_get_contents('/proc/1/cgroup'), 'docker'),
        ];
    }

    /**
     * 2. Check Environment Variables relevant to Chromium & Playwright.
     */
    protected function checkEnvironmentVariables(string $serviceName = 'agent-prod-browser'): array
    {
        $home = getenv('HOME') ?: ($_SERVER['HOME'] ?? null);
        $playwrightBrowsersPath = env('PLAYWRIGHT_BROWSERS_PATH') ?: getenv('PLAYWRIGHT_BROWSERS_PATH');
        $playwrightRuntimeDir = env('PLAYWRIGHT_RUNTIME_DIR') ?: getenv('PLAYWRIGHT_RUNTIME_DIR') ?: '/tmp/playwright-agent-runtime';
        $xdgConfig = getenv('XDG_CONFIG_HOME') ?: ($_SERVER['XDG_CONFIG_HOME'] ?? null);
        $xdgCache = getenv('XDG_CACHE_HOME') ?: ($_SERVER['XDG_CACHE_HOME'] ?? null);
        $tmpDir = getenv('TMPDIR') ?: sys_get_temp_dir();

        $homeWritable = $home && is_dir($home) && is_writable($home);
        $homeIssue = false;

        if (! $home || $home === '/nonexistent') {
            $this->crashpadIssues[] = "HOME environment variable is unset or points to '/nonexistent'. Chromium Crashpad requires a valid writable HOME or XDG_CONFIG_HOME to initialize its crash database.";
            $this->remediations[] = "Ensure HOME=/tmp/playwright-agent-runtime is set in /etc/systemd/system/{$serviceName}.service";
            $homeIssue = true;
            $this->allPassed = false;
        } elseif (! $homeWritable) {
            $this->crashpadIssues[] = "HOME directory '{$home}' is not writable by the current user. Crashpad fails when attempting to create ~/.config/Crashpad.";
            $this->remediations[] = 'sudo chown -R '.($this->getProcessUser() ?: 'www-data').':'.($this->getProcessUser() ?: 'www-data')." {$home}";
            $homeIssue = true;
            $this->allPassed = false;
        }

        return [
            'HOME' => $home,
            'HOME_writable' => $homeWritable,
            'HOME_issue' => $homeIssue,
            'PLAYWRIGHT_BROWSERS_PATH' => $playwrightBrowsersPath,
            'PLAYWRIGHT_RUNTIME_DIR' => $playwrightRuntimeDir,
            'XDG_CONFIG_HOME' => $xdgConfig,
            'XDG_CACHE_HOME' => $xdgCache,
            'TMPDIR' => $tmpDir,
            'PATH' => getenv('PATH'),
        ];
    }

    /**
     * 3. Check critical filesystem paths, Crashpad database directories, and permissions.
     */
    protected function checkCriticalDirectories(): array
    {
        $runtimeDir = env('PLAYWRIGHT_RUNTIME_DIR') ?: '/tmp/playwright-agent-runtime';
        $cacheDir = '/var/www/.cache';
        $playwrightCache = '/var/www/.cache/ms-playwright';

        $targets = [
            '/tmp' => 'System temporary directory',
            $runtimeDir => 'Playwright runtime directory (HOME for server.js)',
            "{$runtimeDir}/.config" => 'Chromium XDG config directory',
            "{$runtimeDir}/.cache" => 'Chromium XDG cache directory',
            "{$runtimeDir}/crash_dumps" => 'Chromium crash dumps / Crashpad database directory',
            "{$runtimeDir}/user-data" => 'Chromium user data directory',
            "{$runtimeDir}/mcp-config.json" => 'Playwright MCP runtime config',
            $cacheDir => 'Shared web cache directory',
            $playwrightCache => 'Playwright browsers cache directory',
            storage_path('app/temp_scripts') => 'Storage sandbox scripts directory',
            storage_path('logs') => 'Laravel logs directory',
        ];

        $results = [];

        foreach ($targets as $path => $desc) {
            $exists = file_exists($path);
            $isDir = is_dir($path);
            $writable = $exists ? is_writable($path) : false;
            $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : null;
            $owner = null;
            $group = null;

            if ($exists && function_exists('posix_getpwuid')) {
                $ownerInfo = posix_getpwuid(fileowner($path));
                $groupInfo = posix_getgrgid(filegroup($path));
                $owner = $ownerInfo['name'] ?? fileowner($path);
                $group = $groupInfo['name'] ?? filegroup($path);
            }

            // Detect root ownership issues where www-data needs access
            $isRootOwned = ($owner === 'root' || $owner === '0');
            $currentUser = $this->getProcessUser();

            if ($exists && ! $writable) {
                $this->crashpadIssues[] = "Path '{$path}' exists but is not writable (Permissions: {$perms}, Owner: {$owner}:{$group}).";
                $this->remediations[] = "sudo chown -R www-data:www-data {$path} && sudo chmod -R 775 {$path}";
                $this->allPassed = false;
            }

            if ($isRootOwned && $currentUser !== 'root' && in_array($path, [$runtimeDir, $cacheDir, $playwrightCache])) {
                $this->crashpadIssues[] = "Path '{$path}' is owned by root ({$owner}:{$group}). If the server runs as www-data, Chromium Crashpad will fail to create its database.";
                $this->remediations[] = "sudo chown -R www-data:www-data {$path}";
                $this->allPassed = false;
            }

            $results[$path] = [
                'description' => $desc,
                'exists' => $exists,
                'is_dir' => $isDir,
                'writable' => $writable,
                'permissions' => $perms,
                'owner' => $owner,
                'group' => $group,
                'is_root_owned' => $isRootOwned,
            ];
        }

        return $results;
    }

    /**
     * 4. Check Node.js, npm, npx runtimes.
     */
    protected function checkNodeRuntime(): array
    {
        $nodePath = $this->findBinary('node');
        $npmPath = $this->findBinary('npm');
        $npxPath = $this->findBinary('npx');

        $nodeVersion = $nodePath ? $this->executeShell("{$nodePath} -v") : null;
        $npmVersion = $npmPath ? $this->executeShell("{$npmPath} -v") : null;
        $npxVersion = $npxPath ? $this->executeShell("{$npxPath} -v") : null;

        $hasStandardSymlink = file_exists('/usr/local/bin/node') || file_exists('/usr/bin/node');

        if (! $nodePath) {
            $this->allPassed = false;
            $this->remediations[] = 'curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt install -y nodejs';
        }

        if ($nodePath && ! file_exists('/usr/local/bin/node')) {
            $this->remediations[] = 'sudo ln -sfn $(which node) /usr/local/bin/node';
            $this->remediations[] = 'sudo ln -sfn $(which npx) /usr/local/bin/npx';
        }

        return [
            'node_path' => $nodePath,
            'node_version' => $nodeVersion,
            'npm_path' => $npmPath,
            'npm_version' => $npmVersion,
            'npx_path' => $npxPath,
            'npx_version' => $npxVersion,
            'has_standard_symlink' => $hasStandardSymlink,
        ];
    }

    /**
     * 5. Check npm package dependencies.
     */
    protected function checkNpmPackages(): array
    {
        $packages = [
            'playwright' => 'node_modules/playwright/package.json',
            '@playwright/mcp' => 'node_modules/@playwright/mcp/package.json',
            'express' => 'node_modules/express/package.json',
            '@modelcontextprotocol/sdk' => 'node_modules/@modelcontextprotocol/sdk/package.json',
        ];

        $results = [];

        foreach ($packages as $pkgName => $relPath) {
            $fullPath = base_path($relPath);
            $installed = file_exists($fullPath);
            $version = null;

            if ($installed) {
                $content = @file_get_contents($fullPath);
                $json = json_decode((string) $content, true);
                $version = $json['version'] ?? 'installed';
            } else {
                $this->allPassed = false;
                $this->remediations[] = "npm install {$pkgName}";
            }

            $results[$pkgName] = [
                'installed' => $installed,
                'version' => $version,
                'path' => $fullPath,
            ];
        }

        return $results;
    }

    /**
     * 6. Inspect browser installations, Chrome for Testing, crashpad handler, and shared libraries (ldd).
     */
    protected function checkBrowserBinaries(): array
    {
        $candidatePaths = array_filter([
            env('PLAYWRIGHT_BROWSERS_PATH'),
            getenv('PLAYWRIGHT_BROWSERS_PATH'),
            '/var/www/.cache/ms-playwright',
            (getenv('HOME') ?: '/root').'/.cache/ms-playwright',
            base_path('node_modules/playwright/.local-browsers'),
        ]);

        $discovered = [];
        $chromeBinary = null;
        $crashpadHandlerBinary = null;
        $missingLibraries = [];

        foreach ($candidatePaths as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $files = @scandir($dir) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $fullDir = "{$dir}/{$file}";
                if (is_dir($fullDir)) {
                    $discovered[] = [
                        'folder' => $file,
                        'path' => $fullDir,
                    ];

                    // Look for Chrome executable
                    $possibleChromes = [
                        "{$fullDir}/chrome-linux64/chrome",
                        "{$fullDir}/chrome-linux/chrome",
                        "{$fullDir}/chrome-win64/chrome.exe",
                        "{$fullDir}/chrome-mac/Chromium.app/Contents/MacOS/Chromium",
                    ];
                    foreach ($possibleChromes as $p) {
                        if (file_exists($p)) {
                            $chromeBinary = $p;
                            break;
                        }
                    }

                    // Look for Crashpad handler executable
                    $possibleCrashpads = [
                        "{$fullDir}/chrome-linux64/chrome_crashpad_handler",
                        "{$fullDir}/chrome-linux/chrome_crashpad_handler",
                        "{$fullDir}/chrome-win64/chrome_crashpad_handler.exe",
                    ];
                    foreach ($possibleCrashpads as $cp) {
                        if (file_exists($cp)) {
                            $crashpadHandlerBinary = $cp;
                            break;
                        }
                    }
                }
            }
        }

        // Run ldd on Linux to detect missing dynamic shared libraries
        if ($chromeBinary && PHP_OS_FAMILY === 'Linux' && $this->findBinary('ldd')) {
            $lddOutput = $this->executeShell("ldd '{$chromeBinary}' 2>&1");
            foreach (explode("\n", (string) $lddOutput) as $line) {
                if (str_contains(strtolower($line), 'not found')) {
                    $missingLibraries[] = trim($line);
                }
            }

            if (count($missingLibraries) > 0) {
                $this->crashpadIssues[] = 'Missing Linux shared libraries detected for Chromium binary. This causes immediate Chromium process crash which prompts crashpad to abort.';
                $this->remediations[] = 'sudo npx playwright install --with-deps chromium';
                $this->allPassed = false;
            }
        }

        if (! $chromeBinary) {
            $this->allPassed = false;
            $this->remediations[] = 'sudo PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright npx @playwright/mcp install-browser chrome-for-testing';
        }

        return [
            'candidate_paths' => array_values(array_unique($candidatePaths)),
            'discovered_folders' => $discovered,
            'chrome_binary' => $chromeBinary,
            'chrome_executable' => $chromeBinary ? is_executable($chromeBinary) : false,
            'crashpad_handler_binary' => $crashpadHandlerBinary,
            'crashpad_handler_executable' => $crashpadHandlerBinary ? is_executable($crashpadHandlerBinary) : false,
            'missing_libraries' => $missingLibraries,
        ];
    }

    /**
     * 7. Check systemd service status and journalctl logs.
     */
    protected function checkSystemdService(string $serviceName): array
    {
        $serviceName = preg_replace('/\.service$/', '', trim($serviceName)) ?: 'agent-prod-browser';
        $hasSystemctl = (bool) $this->findBinary('systemctl');
        $serviceFile = "/etc/systemd/system/{$serviceName}.service";
        $serviceExists = file_exists($serviceFile);
        $isActive = false;
        $isEnabled = false;
        $statusOutput = null;
        $recentLogs = [];
        $serviceConfig = [];

        if ($serviceExists) {
            $content = (string) @file_get_contents($serviceFile);
            preg_match('/User\s*=\s*(.*)/i', $content, $mUser);
            preg_match('/Group\s*=\s*(.*)/i', $content, $mGroup);
            preg_match('/Environment\s*=\s*HOME=(.*)/i', $content, $mHome);
            preg_match('/Environment\s*=\s*PLAYWRIGHT_BROWSERS_PATH=(.*)/i', $content, $mBrowsers);
            preg_match('/ExecStart\s*=\s*(.*)/i', $content, $mExec);

            $serviceConfig = [
                'User' => trim($mUser[1] ?? ''),
                'Group' => trim($mGroup[1] ?? ''),
                'HOME' => trim($mHome[1] ?? ''),
                'PLAYWRIGHT_BROWSERS_PATH' => trim($mBrowsers[1] ?? ''),
                'ExecStart' => trim($mExec[1] ?? ''),
            ];

            // If HOME is not configured in service, warn
            if (empty($serviceConfig['HOME'])) {
                $this->crashpadIssues[] = "Systemd service '{$serviceFile}' does not define 'Environment=HOME=/tmp/playwright-agent-runtime'. www-data defaults to /nonexistent or /var/www, causing Crashpad database errors.";
                $this->remediations[] = "Add 'Environment=HOME=/tmp/playwright-agent-runtime' to {$serviceFile} and run: sudo systemctl daemon-reload && sudo systemctl restart {$serviceName}";
                $this->allPassed = false;
            }
        }

        if ($hasSystemctl) {
            $isActive = trim((string) $this->executeShell("systemctl is-active {$serviceName} 2>/dev/null")) === 'active';
            $isEnabled = trim((string) $this->executeShell("systemctl is-enabled {$serviceName} 2>/dev/null")) === 'enabled';
            $statusOutput = $this->executeShell("systemctl status {$serviceName} --no-pager 2>/dev/null");

            // Read last 15 lines from journalctl
            $rawLogs = $this->executeShell("journalctl -u {$serviceName} -n 15 --no-pager 2>/dev/null");
            if ($rawLogs) {
                $recentLogs = array_filter(array_map('trim', explode("\n", (string) $rawLogs)));
            }

            if (! $isActive) {
                $this->remediations[] = "sudo systemctl restart {$serviceName}.service";
            }
        }

        return [
            'service_name' => $serviceName,
            'service_file' => $serviceFile,
            'service_file_exists' => $serviceExists,
            'service_config' => $serviceConfig,
            'is_active' => $isActive,
            'is_enabled' => $isEnabled,
            'status_snippet' => $statusOutput ? substr($statusOutput, 0, 500) : null,
            'recent_logs' => array_values($recentLogs),
        ];
    }

    /**
     * 8. Check Playwright Server Bridge Script (playwright-server/server.js).
     */
    protected function checkBridgeScript(): array
    {
        $serverJsPath = base_path('playwright-server/server.js');
        $exists = file_exists($serverJsPath);
        $hasDisableCrashpad = false;
        $hasCrashDumpsDir = false;
        $hasUserDataDirArg = false;

        if ($exists) {
            $content = (string) @file_get_contents($serverJsPath);
            $hasDisableCrashpad = str_contains($content, '--disable-crashpad');
            $hasCrashDumpsDir = str_contains($content, '--crash-dumps-dir');
            $hasUserDataDirArg = str_contains($content, '--user-data-dir');

            if ($hasUserDataDirArg) {
                $this->crashpadIssues[] = "playwright-server/server.js contains '--user-data-dir' in launchOptions.args. Playwright explicitly forbids this argument in launch() and throws 'Error: async initializeServer' (_createUserDataDirArgMisuseError).";
                $this->remediations[] = "Remove '--user-data-dir' from launchOptions.args in playwright-server/server.js and restart the service.";
                $this->allPassed = false;
            }
        }

        return [
            'path' => $serverJsPath,
            'exists' => $exists,
            'has_disable_crashpad_flag' => $hasDisableCrashpad,
            'has_crash_dumps_dir_flag' => $hasCrashDumpsDir,
            'has_user_data_dir_arg' => $hasUserDataDirArg,
        ];
    }

    /**
     * 9. Check Playwright MCP Server Daemon & HTTP Port.
     */
    protected function checkServerDaemon(): array
    {
        $port = 3030;
        $host = 'http://127.0.0.1';
        $healthUrl = "{$host}:{$port}/health";

        $reachable = false;
        $healthData = null;
        $latencyMs = 0;
        $errorMsg = null;

        try {
            $start = microtime(true);
            $resp = Http::timeout(2)->get($healthUrl);
            $latencyMs = round((microtime(true) - $start) * 1000, 1);
            if ($resp->successful()) {
                $reachable = true;
                $healthData = $resp->json();
            } else {
                $errorMsg = "HTTP Status {$resp->status()}";
            }
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }

        // Port check via ss/netstat
        $portBound = false;
        $portProcess = null;
        if ($this->findBinary('ss')) {
            $ssOut = $this->executeShell("ss -tulpn | grep ':{$port}'");
            if (! empty($ssOut)) {
                $portBound = true;
                $portProcess = trim($ssOut);
            }
        }

        return [
            'endpoint' => "{$host}:{$port}/mcp",
            'health_url' => $healthUrl,
            'reachable' => $reachable,
            'latency_ms' => $latencyMs,
            'response' => $healthData,
            'error' => $errorMsg,
            'port_bound' => $portBound,
            'port_process' => $portProcess,
        ];
    }

    /**
     * 10. Check Database Registration for Playwright MCP server.
     */
    protected function checkDatabaseRegistration(): array
    {
        $server = McpServer::where('name', 'like', '%Playwright%')
            ->orWhere('endpoint_url', 'like', '%:3030%')
            ->first();

        if (! $server) {
            $this->remediations[] = 'php artisan mcp:register-playwright --sync';

            return [
                'registered' => false,
            ];
        }

        return [
            'registered' => true,
            'id' => $server->id,
            'name' => $server->name,
            'endpoint_url' => $server->endpoint_url,
            'is_active' => (bool) $server->is_active,
            'tools_count' => is_array($server->tools_cache) ? count($server->tools_cache) : 0,
        ];
    }

    /**
     * 11. Live Headless Browser Launch Test.
     */
    protected function runLiveLaunchTest(): array
    {
        $nodePath = $this->findBinary('node');
        if (! $nodePath) {
            return [
                'executed' => false,
                'error' => 'Node.js is not installed or not in PATH.',
            ];
        }

        $runtimeDir = env('PLAYWRIGHT_RUNTIME_DIR') ?: '/tmp/playwright-agent-runtime';
        $browsersPath = env('PLAYWRIGHT_BROWSERS_PATH') ?: '/var/www/.cache/ms-playwright';

        // Inline node verification script
        $script = <<<'JS'
const { chromium } = require('playwright');
(async () => {
    try {
        const browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-crash-reporter',
                '--disable-breakpad',
                '--disable-crashpad'
            ]
        });
        const page = await browser.newPage();
        await page.setContent('<html><head><title>Diagnostic</title></head><body>OK</body></html>');
        const title = await page.title();
        await browser.close();
        console.log(`LAUNCH_SUCCESS: Page title '${title}'`);
        process.exit(0);
    } catch (err) {
        console.error(`LAUNCH_ERROR: ${err.message}`);
        process.exit(1);
    }
})();
JS;

        $process = new Process([$nodePath, '-e', $script], base_path(), [
            'HOME' => $runtimeDir,
            'XDG_CONFIG_HOME' => "{$runtimeDir}/.config",
            'XDG_CACHE_HOME' => "{$runtimeDir}/.cache",
            'PLAYWRIGHT_BROWSERS_PATH' => $browsersPath,
        ]);
        $process->setTimeout(15);
        $process->run();

        $success = $process->isSuccessful() && str_contains($process->getOutput(), 'LAUNCH_SUCCESS');
        $output = trim($process->getOutput());
        $error = trim($process->getErrorOutput());

        if (! $success) {
            $this->allPassed = false;
            $this->crashpadIssues[] = 'Live headless browser test launch failed: '.($error ?: $output);
        }

        return [
            'executed' => true,
            'success' => $success,
            'output' => $output,
            'error' => $error,
        ];
    }

    /**
     * Render Summary Table in CLI.
     */
    protected function renderSummaryTable(): void
    {
        $rows = [];

        // OS & User
        $sys = $this->report['system'];
        $rows[] = [
            'OS & Kernel',
            "{$sys['os']} ({$sys['architecture']}, Kernel: {$sys['kernel']})",
            '<info>PASS</info>',
            $sys['is_container'] ? 'Container/Docker' : 'Host VPS',
        ];

        $userBadge = $sys['is_root'] ? '<comment>WARN</comment>' : '<info>PASS</info>';
        $userNote = $sys['is_root'] ? 'Running as root (service should use www-data)' : "UID: {$sys['uid']}";
        $rows[] = [
            'Current User',
            $sys['current_user'],
            $userBadge,
            $userNote,
        ];

        // Shared Memory
        $shm = $sys['dev_shm'];
        $shmStatus = ($shm['exists'] && $shm['writable']) ? '<info>PASS</info>' : '<error>FAIL</error>';
        $rows[] = [
            'Shared Memory (/dev/shm)',
            "{$shm['free_mb']}MB free / {$shm['total_mb']}MB total",
            $shmStatus,
            $shm['writable'] ? 'Writable' : 'NOT Writable (Crashpad Risk)',
        ];

        // Environment: HOME
        $env = $this->report['environment'];
        $homeStatus = (! $env['HOME_issue']) ? '<info>PASS</info>' : '<error>FAIL</error>';
        $rows[] = [
            'HOME Directory',
            $env['HOME'] ?: '(unset)',
            $homeStatus,
            $env['HOME_writable'] ? 'Writable' : 'Unwritable / Nonexistent',
        ];

        // Node.js
        $node = $this->report['node_runtime'];
        $nodeStatus = $node['node_path'] ? '<info>PASS</info>' : '<error>FAIL</error>';
        $rows[] = [
            'Node.js Runtime',
            $node['node_version'] ?: 'Missing',
            $nodeStatus,
            $node['node_path'] ?: 'node not in PATH',
        ];

        // NPM Packages
        $pkgs = $this->report['packages'];
        $allPkgsInstalled = ! in_array(false, array_column($pkgs, 'installed'), true);
        $pkgStatus = $allPkgsInstalled ? '<info>PASS</info>' : '<error>FAIL</error>';
        $rows[] = [
            'NPM Packages',
            'playwright, @playwright/mcp',
            $pkgStatus,
            $allPkgsInstalled ? 'Installed' : 'Missing packages',
        ];

        // Browser Binary
        $browser = $this->report['browser'];
        $browserStatus = ($browser['chrome_binary'] && $browser['chrome_executable']) ? '<info>PASS</info>' : '<error>FAIL</error>';
        $libNote = count($browser['missing_libraries']) > 0 ? count($browser['missing_libraries']).' missing .so' : 'All libs linked';
        $rows[] = [
            'Chrome for Testing',
            $browser['chrome_binary'] ? 'Found' : 'Missing',
            $browserStatus,
            $libNote,
        ];

        // Systemd Service
        $systemd = $this->report['systemd'];
        $systemdStatus = $systemd['is_active'] ? '<info>PASS</info>' : '<comment>WARN</comment>';
        $rows[] = [
            'Systemd Service',
            $systemd['service_name'].($systemd['is_active'] ? ' (Active)' : ' (Inactive)'),
            $systemdStatus,
            $systemd['service_file_exists'] ? $systemd['service_file'] : 'Service file missing',
        ];

        // Playwright Bridge Config
        $bridge = $this->report['bridge_config'];
        $bridgeOk = $bridge['has_disable_crashpad_flag'] && $bridge['has_crash_dumps_dir_flag'] && ! ($bridge['has_user_data_dir_arg'] ?? false);
        $bridgeStatus = $bridgeOk ? '<info>PASS</info>' : '<comment>WARN</comment>';
        $bridgeNote = ($bridge['has_user_data_dir_arg'] ?? false)
            ? 'Conflict: --user-data-dir in args'
            : 'Crashpad flags configured';
        $rows[] = [
            'Bridge Script Config',
            $bridge['exists'] ? 'playwright-server/server.js' : 'Missing',
            $bridgeStatus,
            $bridgeNote,
        ];

        // Server Daemon
        $daemon = $this->report['daemon'];
        $daemonStatus = $daemon['reachable'] ? '<info>PASS</info>' : '<comment>WARN</comment>';
        $rows[] = [
            'Playwright MCP Daemon',
            $daemon['reachable'] ? "Online ({$daemon['latency_ms']}ms)" : 'Offline',
            $daemonStatus,
            $daemon['health_url'],
        ];

        // Database
        $db = $this->report['database'];
        $dbStatus = ($db['registered'] && ($db['is_active'] ?? false)) ? '<info>PASS</info>' : '<comment>WARN</comment>';
        $rows[] = [
            'Database Registration',
            $db['registered'] ? "Active (ID: {$db['id']})" : 'Not Registered',
            $dbStatus,
            'mcp_servers table',
        ];

        $this->table(['Check Component', 'Details', 'Status', 'Notes'], $rows);
    }

    /**
     * Render Crashpad & Permissions Diagnostic Breakdown.
     */
    protected function renderCrashpadDiagnosis(): void
    {
        $this->newLine();
        $this->info('--- Critical Paths & Permissions Audit ---');

        $pathRows = [];
        foreach ($this->report['paths'] as $path => $meta) {
            $existsStr = $meta['exists'] ? 'Yes' : 'No';
            $permsStr = $meta['permissions'] ?? '-';
            $ownerStr = $meta['owner'] ? "{$meta['owner']}:{$meta['group']}" : '-';
            $writeStatus = $meta['writable'] ? '<info>Writable</info>' : '<error>Unwritable</error>';

            $pathRows[] = [
                $meta['description'],
                $path,
                $existsStr,
                $permsStr,
                $ownerStr,
                $writeStatus,
            ];
        }

        $this->table(
            ['Purpose', 'Path', 'Exists', 'Perms', 'Owner', 'Writability'],
            $pathRows
        );

        if (count($this->crashpadIssues) > 0) {
            $this->newLine();
            $this->error('================================================================');
            $this->error(' [!] CRASHPAD & CHROMIUM ISSUES IDENTIFIED');
            $this->error('================================================================');
            foreach ($this->crashpadIssues as $idx => $issue) {
                $num = $idx + 1;
                $this->line(" <error>{$num}.</error> {$issue}");
            }
        }
    }

    /**
     * Render actionable remediation commands.
     */
    protected function renderRemediationGuide(): void
    {
        $uniqueRemediations = array_values(array_unique($this->remediations));

        if (empty($uniqueRemediations)) {
            return;
        }

        $this->newLine();
        $this->info('================================================================');
        $this->info('              Recommended Fix Commands for Ubuntu VPS           ');
        $this->info('================================================================');
        $this->comment('Run the following commands on your VPS terminal to resolve issues:');
        $this->newLine();

        foreach ($uniqueRemediations as $cmd) {
            $this->line("  <info>$</info> <comment>{$cmd}</comment>");
        }
        $this->newLine();
    }

    /**
     * Safely find executable binary in PATH.
     */
    protected function findBinary(string $binary): ?string
    {
        try {
            $process = new Process(['which', $binary]);
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());

                return $path !== '' ? $path : null;
            }
        } catch (Exception $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Run shell command and return stdout.
     */
    protected function executeShell(string $command): ?string
    {
        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(5);
            $process->run();

            return trim($process->getOutput());
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Parse /etc/os-release into an associative array.
     */
    protected function parseOsRelease(): array
    {
        $path = '/etc/os-release';
        if (! file_exists($path)) {
            return ['PRETTY_NAME' => PHP_OS];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $data = [];
        foreach ($lines as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $data[trim($k)] = trim($v, "\"' ");
            }
        }

        return $data;
    }

    /**
     * Resolve the target systemd service name with automatic discovery.
     */
    protected function resolveServiceName(?string $optionValue = null): string
    {
        // 1. Explicit CLI option
        if (! empty($optionValue)) {
            return preg_replace('/\.service$/', '', trim($optionValue));
        }

        // 2. Configuration or environment variable
        $envService = config('services.playwright.systemd_service') ?: env('PLAYWRIGHT_SYSTEMD_SERVICE');
        if (! empty($envService)) {
            return preg_replace('/\.service$/', '', trim((string) $envService));
        }

        // 3. Auto-detect from filesystem (/etc/systemd/system/*.service)
        $candidates = [
            'agent-prod-browser',
            'agent-browser',
            'playwright-browser',
            'playwright-mcp',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists("/etc/systemd/system/{$candidate}.service") ||
                file_exists("/lib/systemd/system/{$candidate}.service")) {
                return $candidate;
            }
        }

        // 4. Fallback to active systemd unit check if systemctl exists
        if ($this->findBinary('systemctl')) {
            foreach ($candidates as $candidate) {
                $status = trim((string) $this->executeShell("systemctl is-active {$candidate} 2>/dev/null"));
                if (in_array($status, ['active', 'activating', 'failed', 'inactive'])) {
                    return $candidate;
                }
            }
        }

        // 5. Default to user's daemon service name: agent-prod-browser
        return 'agent-prod-browser';
    }

    /**
     * Resolve the actual operating system user executing the command.
     */
    protected function getProcessUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $user = posix_getpwuid(posix_geteuid());
            if (! empty($user['name'])) {
                return $user['name'];
            }
        }

        $whoami = $this->executeShell('whoami');
        if (! empty($whoami)) {
            return trim($whoami);
        }

        return get_current_user() ?: 'unknown';
    }
}
