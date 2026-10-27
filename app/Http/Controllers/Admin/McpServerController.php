<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpServer;
use App\Services\McpService;
use Illuminate\Http\Request;

class McpServerController extends Controller
{
    public function __construct(
        protected McpService $mcpService
    ) {}

    public function index()
    {
        $servers = McpServer::latest()->paginate(15);

        return view('admin.mcp_servers.index', compact('servers'));
    }

    public function create()
    {
        return view('admin.mcp_servers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'endpoint_url' => 'required|url|max:255',
            'auth_type' => 'required|in:none,bearer,custom_header',
            'auth_token' => 'nullable|string',
            'custom_header_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $server = McpServer::create($validated);

        // Auto sync tools upon registration
        $result = $this->mcpService->syncTools($server);

        if ($result['success']) {
            return redirect()->route('admin.mcp-servers.index')
                ->with('success', "MCP Server '{$server->name}' registered. Discovered {$result['count']} tools.");
        }

        return redirect()->route('admin.mcp-servers.index')
            ->with('error', "MCP Server '{$server->name}' registered, but could not discover tools: {$result['error']}");
    }

    public function edit(McpServer $mcpServer)
    {
        return view('admin.mcp_servers.edit', ['server' => $mcpServer]);
    }

    public function update(Request $request, McpServer $mcpServer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'endpoint_url' => 'required|url|max:255',
            'auth_type' => 'required|in:none,bearer,custom_header',
            'auth_token' => 'nullable|string',
            'custom_header_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $mcpServer->update($validated);

        // Re-sync tools on update
        $result = $this->mcpService->syncTools($mcpServer);

        if ($result['success']) {
            return redirect()->route('admin.mcp-servers.index')
                ->with('success', "MCP Server '{$mcpServer->name}' updated and synced {$result['count']} tools.");
        }

        return redirect()->route('admin.mcp-servers.index')
            ->with('error', "MCP Server '{$mcpServer->name}' updated, but sync failed: {$result['error']}");
    }

    public function sync(Request $request, McpServer $mcpServer)
    {
        $result = $this->mcpService->syncTools($mcpServer);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $result['success'],
                'count' => $result['count'] ?? 0,
                'tools' => $result['tools'] ?? [],
                'error' => $result['error'] ?? null,
                'message' => $result['success']
                    ? "Discovered {$result['count']} tools from {$mcpServer->name}."
                    : "Failed to sync: {$result['error']}",
                'last_sync_at' => $mcpServer->last_sync_at ? $mcpServer->last_sync_at->diffForHumans() : 'Just now',
            ]);
        }

        if ($result['success']) {
            return back()->with('success', "Discovered {$result['count']} tools from {$mcpServer->name}.");
        }

        return back()->with('error', "Failed to sync tools: {$result['error']}");
    }

    public function toggle(McpServer $mcpServer)
    {
        $mcpServer->update(['is_active' => ! $mcpServer->is_active]);

        return back()->with('success', "MCP Server '{$mcpServer->name}' status toggled.");
    }

    public function destroy(McpServer $mcpServer)
    {
        $mcpServer->delete();

        return redirect()->route('admin.mcp-servers.index')->with('success', 'MCP Server removed.');
    }
}
