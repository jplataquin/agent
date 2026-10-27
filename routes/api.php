<?php

use App\Http\Controllers\BuiltInMcpController;
use App\Http\Controllers\GeminiDiagnosticController;
use Illuminate\Support\Facades\Route;

// Standard HTTP Model Context Protocol (MCP) Endpoint
Route::post('/mcp', [BuiltInMcpController::class, 'handle']);

// Remote Gemini 3 Diagnostics API Endpoint
Route::get('/diagnostics/gemini/status', [GeminiDiagnosticController::class, 'status']);
Route::post('/diagnostics/gemini/run', [GeminiDiagnosticController::class, 'apiRun']);
