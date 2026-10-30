<?php

use App\Http\Controllers\Admin\BuiltInToolController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LlmProviderController;
use App\Http\Controllers\Admin\MasterPromptController;
use App\Http\Controllers\Admin\McpDiagnosticsController;
use App\Http\Controllers\Admin\McpServerController;
use App\Http\Controllers\Admin\SkillController;
use App\Http\Controllers\Admin\TokenMetricController;
use App\Http\Controllers\Admin\UserChatController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('chat.index');
    }

    return view('welcome');
});

Auth::routes();

Route::get('/home', function () {
    return redirect()->route('chat.index');
})->name('home');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/all-tools-diagnostic', fn () => redirect()->route('admin.all-tools-diagnostic.index'));
    Route::get('/all-tools-diagnostics', fn () => redirect()->route('admin.all-tools-diagnostic.index'));
    Route::get('/mcp-diagnostics', fn () => redirect()->route('admin.all-tools-diagnostic.index'));
});

// Authenticated User Chat Routes
Route::middleware('auth')->prefix('chat')->name('chat.')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('index');
    Route::post('/', [ChatController::class, 'store'])->name('store');
    Route::get('/{session}', [ChatController::class, 'show'])->name('show');
    Route::delete('/{session}', [ChatController::class, 'destroy'])->name('destroy');
    Route::patch('/{session}/rename', [ChatController::class, 'rename'])->name('rename');
    Route::post('/{session}/pin', [ChatController::class, 'togglePin'])->name('pin');
    Route::post('/{session}/regenerate', [ChatController::class, 'regenerate'])->name('regenerate');
    Route::put('/{session}/messages/{message}', [ChatController::class, 'editMessage'])->name('messages.edit');
    Route::post('/{session}/messages/{message}/feedback', [ChatController::class, 'feedback'])->name('messages.feedback');
    Route::post('/{session}/message', [ChatController::class, 'sendMessage'])->name('message');
    Route::get('/{session}/stream', [ChatController::class, 'stream'])->name('stream');
    Route::post('/{session}/wrap-up', [ChatController::class, 'wrapUp'])->name('wrap-up');
    Route::post('/{session}/cancel', [ChatController::class, 'cancel'])->name('cancel');
});

// Admin Console Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Users Management
    Route::resource('users', UserController::class);

    // AI Skills Management
    Route::post('skills/doctor', [SkillController::class, 'doctor'])->name('skills.doctor');
    Route::match(['get', 'post'], 'skills/doctor/report', [SkillController::class, 'doctorReport'])->name('skills.doctor.report');
    Route::match(['get', 'post'], 'skills/{skill}/preview', [SkillController::class, 'preview'])->name('skills.preview');
    Route::resource('skills', SkillController::class);
    Route::post('skills/{skill}/toggle', [SkillController::class, 'toggle'])->name('skills.toggle');

    // HTTP MCP Servers Management
    Route::resource('mcp-servers', McpServerController::class);
    Route::post('mcp-servers/{mcp_server}/sync', [McpServerController::class, 'sync'])->name('mcp-servers.sync');
    Route::post('mcp-servers/{mcp_server}/toggle', [McpServerController::class, 'toggle'])->name('mcp-servers.toggle');

    // Native Internal Tools Test UI
    Route::get('internal-tools', [BuiltInToolController::class, 'index'])->name('internal-tools.index');
    Route::post('internal-tools/test', [BuiltInToolController::class, 'test'])->name('internal-tools.test');
    // Backward compatibility aliases
    Route::get('built-in-tools', [BuiltInToolController::class, 'index'])->name('built-in-tools.index');
    Route::post('built-in-tools/test', [BuiltInToolController::class, 'test'])->name('built-in-tools.test');

    // All Tools Diagnostic & Subsystem Health Audit
    Route::get('all-tools-diagnostic', [McpDiagnosticsController::class, 'index'])->name('all-tools-diagnostic.index');
    Route::get('all-tools-diagnostics', [McpDiagnosticsController::class, 'index'])->name('all-tools-diagnostics.index');
    Route::post('all-tools-diagnostic/run', [McpDiagnosticsController::class, 'runDiagnostics'])->name('all-tools-diagnostic.run');
    Route::post('all-tools-diagnostic/action', [McpDiagnosticsController::class, 'quickAction'])->name('all-tools-diagnostic.action');
    // Backward compatibility aliases
    Route::get('mcp-diagnostics', [McpDiagnosticsController::class, 'index'])->name('mcp-diagnostics.index');
    Route::post('mcp-diagnostics/run', [McpDiagnosticsController::class, 'runDiagnostics'])->name('mcp-diagnostics.run');
    Route::post('mcp-diagnostics/action', [McpDiagnosticsController::class, 'quickAction'])->name('mcp-diagnostics.action');

    // Master Prompts Management
    Route::resource('master-prompts', MasterPromptController::class);
    Route::post('master-prompts/{master_prompt}/set-active', [MasterPromptController::class, 'setActive'])->name('master-prompts.set-active');

    // LLM Providers & API Credentials
    Route::post('llm-providers/fetch-models', [LlmProviderController::class, 'fetchModels'])->name('llm-providers.fetch-models');
    Route::resource('llm-providers', LlmProviderController::class);
    Route::post('llm-providers/{llm_provider}/set-default', [LlmProviderController::class, 'setDefault'])->name('llm-providers.set-default');
    Route::post('llm-providers/{llm_provider}/set-router', [LlmProviderController::class, 'setRouter'])->name('llm-providers.set-router');
    Route::post('llm-providers/{llm_provider}/test', [LlmProviderController::class, 'testConnection'])->name('llm-providers.test');
    Route::get('llm-providers/{llm_provider}/logs', [LlmProviderController::class, 'logs'])->name('llm-providers.logs');
    Route::delete('llm-providers/{llm_provider}/logs', [LlmProviderController::class, 'clearLogs'])->name('llm-providers.logs.clear');
    Route::post('llm-providers/{llm_provider}/test-prompt', [LlmProviderController::class, 'testPrompt'])->name('llm-providers.test-prompt');

    // Token Consumption Metrics
    Route::get('metrics', [TokenMetricController::class, 'index'])->name('metrics.index');

    // Regular User Chat History & Conversation Audits (Admin Only)
    Route::get('user-chats', [UserChatController::class, 'index'])->name('user-chats.index');
    Route::get('user-chats/{session}', [UserChatController::class, 'show'])->name('user-chats.show');
    Route::get('user-chats/{session}/export', [UserChatController::class, 'export'])->name('user-chats.export');
    Route::delete('user-chats/{session}', [UserChatController::class, 'destroy'])->name('user-chats.destroy');
});

// PWA Service Worker & Manifest Routes
Route::get('/manifest.json', function () {
    $path = public_path('manifest.json');
    abort_unless(file_exists($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    abort_unless(file_exists($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('pwa.sw');

Route::get('/offline', function () {
    $path = public_path('offline.html');
    abort_unless(file_exists($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->name('pwa.offline');

Route::get('/offline.html', function () {
    $path = public_path('offline.html');
    abort_unless(file_exists($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
});
