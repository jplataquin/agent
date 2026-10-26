<?php

use App\Http\Controllers\BuiltInMcpController;
use Illuminate\Support\Facades\Route;

// Standard HTTP Model Context Protocol (MCP) Endpoint
Route::post('/mcp', [BuiltInMcpController::class, 'handle']);
