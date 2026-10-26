<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LlmProviderController;
use App\Http\Controllers\Admin\MasterPromptController;
use App\Http\Controllers\Admin\McpServerController;
use App\Http\Controllers\Admin\SkillController;
use App\Http\Controllers\Admin\TokenMetricController;
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
});

// Admin Console Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Users Management
    Route::resource('users', UserController::class);

    // AI Skills Management
    Route::resource('skills', SkillController::class);
    Route::post('skills/{skill}/toggle', [SkillController::class, 'toggle'])->name('skills.toggle');

    // HTTP MCP Servers Management
    Route::resource('mcp-servers', McpServerController::class);
    Route::post('mcp-servers/{mcp_server}/sync', [McpServerController::class, 'sync'])->name('mcp-servers.sync');
    Route::post('mcp-servers/{mcp_server}/toggle', [McpServerController::class, 'toggle'])->name('mcp-servers.toggle');

    // Master Prompts Management
    Route::resource('master-prompts', MasterPromptController::class);
    Route::post('master-prompts/{master_prompt}/set-active', [MasterPromptController::class, 'setActive'])->name('master-prompts.set-active');

    // LLM Providers & API Credentials
    Route::resource('llm-providers', LlmProviderController::class);
    Route::post('llm-providers/{llm_provider}/set-default', [LlmProviderController::class, 'setDefault'])->name('llm-providers.set-default');
    Route::post('llm-providers/{llm_provider}/test', [LlmProviderController::class, 'testConnection'])->name('llm-providers.test');

    // Token Consumption Metrics
    Route::get('metrics', [TokenMetricController::class, 'index'])->name('metrics.index');
});
