<?php

use App\Http\Middleware\AdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$basePath = (isset($_SERVER['APP_BASE_PATH']) && is_dir($_SERVER['APP_BASE_PATH'])) ? $_SERVER['APP_BASE_PATH'] : dirname(__DIR__);

return Application::configure(basePath: $basePath)
    ->withRouting(
        web: $basePath.'/routes/web.php',
        api: $basePath.'/routes/api.php',
        commands: $basePath.'/routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
