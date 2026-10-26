<?php

namespace Tests\Feature;

use App\Services\BuiltInToolsService;
use Tests\TestCase;

class CheckDockerPythonCommandTest extends TestCase
{
    public function test_docker_python_check_command_runs_diagnostics()
    {
        $this->artisan('docker:python-check')
            ->expectsOutputToContain('Docker Python Sandbox Health Check')
            ->expectsOutputToContain('BuiltInToolsService::executePythonScript')
            ->expectsOutputToContain('Target Docker Image : python:3.15-slim');
    }

    public function test_docker_python_check_normalizes_dot_syntax_in_image()
    {
        $this->artisan('docker:python-check', [
            '--image' => 'python.3.15-slim',
        ])
            ->expectsOutputToContain("Notice: Normalized image name 'python.3.15-slim' to Docker tag 'python:3.15-slim'.")
            ->expectsOutputToContain('Target Docker Image : python:3.15-slim');
    }

    public function test_builtin_tools_service_uses_configured_docker_image()
    {
        config(['services.python_sandbox.docker_image' => 'python:3.15-slim']);
        $this->assertEquals('python:3.15-slim', config('services.python_sandbox.docker_image'));

        $tools = app(BuiltInToolsService::class);
        $result = $tools->executePythonScript('print(12 * 12)');

        $this->assertTrue($result['success']);
        $this->assertEquals('144', trim($result['output']));
    }
}
