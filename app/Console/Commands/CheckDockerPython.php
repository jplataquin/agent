<?php

namespace App\Console\Commands;

use App\Services\BuiltInToolsService;
use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('docker:python-check {--image= : Docker Python image tag (default: env PYTHON_DOCKER_IMAGE or python:3.15-slim)} {--pull : Automatically pull the image if missing locally}')]
#[Description('Diagnose and verify that Docker Python sandbox is installed, running, and functional')]
class CheckDockerPython extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BuiltInToolsService $toolsService): int
    {
        $this->newLine();
        $this->info('======================================================');
        $this->info('       Docker Python Sandbox Health Check             ');
        $this->info('======================================================');
        $this->newLine();

        $rawImage = (string) ($this->option('image')
            ?: config('services.python_sandbox.docker_image')
            ?: env('PYTHON_DOCKER_IMAGE')
            ?: 'python:3.15-slim');

        // Normalize image tag if formatted with a dot (e.g. python.3.15-slim -> python:3.15-slim)
        $image = $rawImage;
        if (preg_match('/^python\.(\d)/', $image)) {
            $image = (string) preg_replace('/^python\./', 'python:', $image);
            $this->comment("Notice: Normalized image name '{$rawImage}' to Docker tag '{$image}'.");
        }

        $currentUser = get_current_user();
        $this->line("Target Docker Image : <comment>{$image}</comment>");
        $this->line("Current Process User: <comment>{$currentUser}</comment>");
        $this->newLine();

        $results = [];
        $allPassed = true;

        // -----------------------------------------------------------------
        // 1. Docker CLI Binary
        // -----------------------------------------------------------------
        $this->info('Checking Docker CLI binary...');
        $dockerBinary = $this->findBinary('docker');
        if ($dockerBinary) {
            $this->line("  <info>✓</info> Docker CLI found at: {$dockerBinary}");
            $results[] = ['Docker CLI', 'Found', 'PASS', $dockerBinary];
        } else {
            $this->line('  <error>✗</error> Docker CLI not found in PATH.');
            $this->comment('    -> Install Docker: sudo apt update && sudo apt install -y docker.io');
            $results[] = ['Docker CLI', 'Missing', 'FAIL', 'docker not in PATH'];
            $allPassed = false;
        }

        // -----------------------------------------------------------------
        // 2. Docker Daemon & Socket Connection
        // -----------------------------------------------------------------
        $daemonOk = false;
        if ($dockerBinary) {
            $this->info('Checking Docker daemon status...');
            $daemonCheck = new Process(['docker', 'info', '--format', '{{.ServerVersion}}']);
            $daemonCheck->setTimeout(5);
            $daemonCheck->run();

            if ($daemonCheck->isSuccessful()) {
                $version = trim($daemonCheck->getOutput()) ?: 'Running';
                $this->line("  <info>✓</info> Docker daemon is active (Version: {$version})");
                $results[] = ['Docker Daemon', "Active (v{$version})", 'PASS', 'Connected to docker.sock'];
                $daemonOk = true;
            } else {
                $err = trim($daemonCheck->getErrorOutput() ?: $daemonCheck->getOutput());
                $this->line('  <error>✗</error> Cannot connect to Docker daemon.');

                if (str_contains(strtolower($err), 'permission denied')) {
                    $this->warn('    -> Permission denied on /var/run/docker.sock.');
                    $this->comment("       Run: sudo usermod -aG docker {$currentUser}");
                    $this->comment('       For web server (PHP-FPM): sudo usermod -aG docker www-data');
                    $this->comment('       Then restart Docker: sudo systemctl restart docker');
                    $results[] = ['Docker Daemon', 'Permission Denied', 'FAIL', 'User not in docker group'];
                } else {
                    $this->warn("    -> Daemon error: {$err}");
                    $this->comment('       Run: sudo systemctl enable --now docker');
                    $results[] = ['Docker Daemon', 'Not Running', 'FAIL', 'Daemon unreachable'];
                }
                $allPassed = false;
            }
        } else {
            $results[] = ['Docker Daemon', 'Skipped', 'FAIL', 'Docker CLI unavailable'];
            $allPassed = false;
        }

        // -----------------------------------------------------------------
        // 3. Docker Image Verification & Pull
        // -----------------------------------------------------------------
        $imageOk = false;
        if ($daemonOk) {
            $this->info("Checking Docker image '{$image}'...");
            $inspect = new Process(['docker', 'image', 'inspect', $image]);
            $inspect->setTimeout(5);
            $inspect->run();

            if ($inspect->isSuccessful()) {
                $this->line("  <info>✓</info> Docker image '{$image}' is cached locally.");
                $results[] = ['Docker Image', $image, 'PASS', 'Image available locally'];
                $imageOk = true;
            } else {
                $this->warn("  <comment>!</comment> Image '{$image}' is not cached locally.");

                if ($this->option('pull')) {
                    $this->info("    Pulling image '{$image}' from registry...");
                    $pull = new Process(['docker', 'pull', $image]);
                    $pull->setTimeout(300); // Allow up to 5 minutes for download
                    $pull->run(function ($type, $buffer) {
                        $this->output->write($buffer);
                    });

                    if ($pull->isSuccessful()) {
                        $this->line("  <info>✓</info> Successfully pulled '{$image}'.");
                        $results[] = ['Docker Image', $image, 'PASS', 'Pulled successfully'];
                        $imageOk = true;
                    } else {
                        $this->line("  <error>✗</error> Failed to pull '{$image}'.");
                        $results[] = ['Docker Image', $image, 'FAIL', 'Pull failed'];
                        $allPassed = false;
                    }
                } else {
                    $this->comment("    -> To pull this image, run: docker pull {$image}");
                    $this->comment('       Or rerun this command with --pull');
                    $results[] = ['Docker Image', 'Not Found', 'FAIL', "Run: docker pull {$image}"];
                    $allPassed = false;
                }
            }
        } else {
            $results[] = ['Docker Image', 'Skipped', 'FAIL', 'Daemon not accessible'];
            $allPassed = false;
        }

        // -----------------------------------------------------------------
        // 4. Sandbox Container Execution Test
        // -----------------------------------------------------------------
        $sandboxOk = false;
        if ($imageOk) {
            $this->info('Testing isolated container execution (network: none, memory: 256m)...');
            $testScript = 'import sys; print(f"PYTHON_VER:{sys.version.split()[0]}")';
            $runProcess = new Process([
                'docker', 'run', '--rm',
                '--network', 'none',
                '--memory', '256m',
                $image,
                'python', '-c', $testScript,
            ]);
            $runProcess->setTimeout(10);
            $runProcess->run();

            if ($runProcess->isSuccessful() && str_contains($runProcess->getOutput(), 'PYTHON_VER:')) {
                $versionMatch = trim(explode('PYTHON_VER:', $runProcess->getOutput())[1] ?? 'Unknown');
                $this->line("  <info>✓</info> Sandbox container executed successfully (Python {$versionMatch})");
                $results[] = ['Container Sandbox', "Python {$versionMatch}", 'PASS', 'Isolated execution works'];
                $sandboxOk = true;
            } else {
                $err = trim($runProcess->getErrorOutput() ?: $runProcess->getOutput());
                $this->line("  <error>✗</error> Container sandbox execution failed: {$err}");
                $results[] = ['Container Sandbox', 'Execution Error', 'FAIL', $err];
                $allPassed = false;
            }
        } else {
            $results[] = ['Container Sandbox', 'Skipped', 'FAIL', 'Image or daemon unavailable'];
            $allPassed = false;
        }

        // -----------------------------------------------------------------
        // 5. Volume Mount & Temp Script Execution Test
        // -----------------------------------------------------------------
        if ($sandboxOk) {
            $this->info('Testing volume mount of temporary script...');
            $tempDir = storage_path('app/temp_scripts');
            if (! is_dir($tempDir)) {
                @mkdir($tempDir, 0755, true);
            }
            $testFile = $tempDir.'/test_check_'.uniqid().'.py';
            file_put_contents($testFile, "a = 20\nb = 22\nprint(f'SUM:{a + b}')");

            $mountProcess = new Process([
                'docker', 'run', '--rm',
                '--network', 'none',
                '--memory', '256m',
                '-v', "{$testFile}:/app/script.py:ro",
                $image,
                'python', '/app/script.py',
            ]);
            $mountProcess->setTimeout(10);
            $mountProcess->run();

            @unlink($testFile);

            if ($mountProcess->isSuccessful() && str_contains($mountProcess->getOutput(), 'SUM:42')) {
                $this->line('  <info>✓</info> Volume mount read-only test passed (SUM: 42).');
                $results[] = ['Volume Mount', 'Read-Only OK', 'PASS', 'Mounting storage scripts works'];
            } else {
                $err = trim($mountProcess->getErrorOutput() ?: $mountProcess->getOutput());
                $this->line("  <error>✗</error> Volume mount failed: {$err}");
                $results[] = ['Volume Mount', 'Mount Error', 'FAIL', $err];
                $allPassed = false;
            }
        } else {
            $results[] = ['Volume Mount', 'Skipped', 'FAIL', 'Prerequisites not met'];
            $allPassed = false;
        }

        // -----------------------------------------------------------------
        // 6. BuiltInToolsService Integration Test
        // -----------------------------------------------------------------
        $this->info('Testing BuiltInToolsService::executePythonScript()...');
        $serviceResult = $toolsService->executePythonScript("numbers = [1, 2, 3, 4, 5]\nprint(f'Total: {sum(numbers)}')");
        if ($serviceResult['success'] && str_contains($serviceResult['output'], 'Total: 15')) {
            $this->line("  <info>✓</info> BuiltInToolsService executed script: '{$serviceResult['output']}'");
            $results[] = ['App Service Integration', 'Success', 'PASS', trim($serviceResult['output'])];
        } else {
            $this->line("  <error>✗</error> BuiltInToolsService execution failed: {$serviceResult['output']}");
            $results[] = ['App Service Integration', 'Failed', 'FAIL', $serviceResult['output']];
        }

        // -----------------------------------------------------------------
        // 7. Host Local Python Fallback Status
        // -----------------------------------------------------------------
        $this->info('Checking host local Python fallback (python3)...');
        $localPython = $this->findBinary('python3');
        if ($localPython) {
            $localVerProcess = new Process(['python3', '--version']);
            $localVerProcess->run();
            $localVer = trim($localVerProcess->getOutput() ?: $localVerProcess->getErrorOutput());
            $this->line("  <info>✓</info> Host fallback available: {$localVer} ({$localPython})");
            $results[] = ['Host Python Fallback', $localVer, 'INFO', 'Available as backup if Docker stops'];
        } else {
            $this->line('  <comment>!</comment> Host python3 not found.');
            $results[] = ['Host Python Fallback', 'Not installed', 'WARN', 'sudo apt install -y python3'];
        }

        // -----------------------------------------------------------------
        // Diagnostic Summary Table
        // -----------------------------------------------------------------
        $this->newLine();
        $this->table(
            ['Component', 'Details', 'Status', 'Notes'],
            array_map(function ($row) {
                $statusColor = match ($row[2]) {
                    'PASS' => "<info>{$row[2]}</info>",
                    'FAIL' => "<error>{$row[2]}</error>",
                    'WARN' => "<comment>{$row[2]}</comment>",
                    default => "<comment>{$row[2]}</comment>",
                };

                return [$row[0], $row[1], $statusColor, $row[3]];
            }, $results)
        );

        $this->newLine();
        if ($allPassed && $sandboxOk) {
            $this->info('======================================================');
            $this->info(" [SUCCESS] Docker Python ({$image}) is running and fully operational!");
            $this->info('======================================================');

            return self::SUCCESS;
        }

        if ($localPython && ! $sandboxOk) {
            $this->warn('======================================================');
            $this->warn(' [NOTICE] Docker Python is not operational.');
            $this->warn(' The application will fall back to local host python3.');
            $this->warn('======================================================');

            return self::FAILURE;
        }

        $this->error('======================================================');
        $this->error(' [ERROR] Neither Docker Python nor host Python is working.');
        $this->error('======================================================');

        return self::FAILURE;
    }

    /**
     * Find absolute path to binary.
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
            // Ignore process exceptions
        }

        return null;
    }
}
