<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpServer;
use App\Services\McpDiagnosticsService;
use App\Services\McpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class McpDiagnosticsController extends Controller
{
    public function __construct(
        protected McpDiagnosticsService $diagnosticsService,
        protected McpService $mcpService
    ) {}

    /**
     * Display the MCP Diagnostics & Health dashboard.
     */
    public function index(): View
    {
        $audit = $this->diagnosticsService->auditAll();

        return view('admin.mcp_diagnostics.index', compact('audit'));
    }

    /**
     * Run diagnostics and return JSON report (for on-demand AJAX refresh).
     */
    public function runDiagnostics(Request $request): JsonResponse
    {
        $audit = $this->diagnosticsService->auditAll();

        return response()->json([
            'success' => true,
            'audit' => $audit,
        ]);
    }

    /**
     * Perform quick corrective actions directly from the diagnostics UI.
     */
    public function quickAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:sync_server,register_playwright',
            'server_id' => 'nullable|integer',
        ]);

        $action = $validated['action'];

        if ($action === 'sync_server') {
            $serverId = (int) ($validated['server_id'] ?? 0);
            $server = McpServer::findOrFail($serverId);
            $result = $this->mcpService->syncTools($server);

            return response()->json([
                'success' => $result['success'] ?? false,
                'message' => ($result['success'] ?? false)
                    ? "Successfully synced {$result['count']} tools for '{$server->name}'."
                    : 'Sync failed: '.($result['error'] ?? 'Unknown error'),
                'server_id' => $server->id,
            ]);
        }

        if ($action === 'register_playwright') {
            try {
                $exitCode = Artisan::call('mcp:register-playwright', ['--sync' => true]);
                $output = Artisan::output();

                return response()->json([
                    'success' => $exitCode === 0,
                    'message' => 'Playwright MCP server registration executed.',
                    'output' => $output,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to register Playwright MCP: '.$e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid action.',
        ], 400);
    }
}
