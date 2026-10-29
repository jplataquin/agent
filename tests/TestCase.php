<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $base = file_exists(getcwd().'/bootstrap/app.php') ? getcwd() : dirname(__DIR__);

        // Ensure App\ and Tests\ classes load from the active worktree directory before Composer's static classmap
        spl_autoload_register(function ($class) use ($base) {
            if (str_starts_with($class, 'App\\')) {
                $file = $base . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
            if (str_starts_with($class, 'Tests\\')) {
                $file = $base . '/tests/' . str_replace('\\', '/', substr($class, 6)) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
        }, true, true);

        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';
        $_ENV['APP_BASE_PATH'] = $base;
        $_SERVER['APP_BASE_PATH'] = $base;
        putenv("APP_BASE_PATH={$base}");

        $app = require $base.'/bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
