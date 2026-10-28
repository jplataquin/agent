<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BuiltInToolsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuiltInToolController extends Controller
{
    protected BuiltInToolsService $toolsService;

    public function __construct(BuiltInToolsService $toolsService)
    {
        $this->toolsService = $toolsService;
    }

    /**
     * Display the manual testing interface for built-in MCP tools.
     */
    public function index(): View
    {
        $tools = $this->toolsService->getToolsDeclaration();

        return view('admin.built_in_tools.index', compact('tools'));
    }

    /**
     * Execute a built-in MCP tool call manually.
     */
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tool_name' => 'required|string',
            'arguments' => 'nullable|array',
        ]);

        $toolName = $validated['tool_name'];
        $arguments = $validated['arguments'] ?? [];

        $startTime = microtime(true);
        $result = $this->toolsService->execute($toolName, $arguments);
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'tool' => $toolName,
            'arguments' => $arguments,
            'duration_ms' => $durationMs,
            'result' => $result,
        ]);
    }
}
