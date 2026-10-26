<?php

namespace App\Services;

use App\Models\McpServer;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McpService
{
    public function __construct(
        protected ?BuiltInToolsService $builtInTools = null
    ) {
        $this->builtInTools = $builtInTools ?? app(BuiltInToolsService::class);
    }

    /**
     * Get headers for an MCP server request.
     */
    protected function getHeaders(McpServer $server): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($server->auth_type === 'bearer' && ! empty($server->auth_token)) {
            $headers['Authorization'] = 'Bearer '.$server->auth_token;
        }

        if (is_array($server->custom_headers)) {
            $headers = array_merge($headers, $server->custom_headers);
        }

        return $headers;
    }

    /**
     * Check if the MCP server endpoint refers to our internal built-in server.
     */
    protected function isBuiltInServer(McpServer $server): bool
    {
        return str_contains($server->endpoint_url, '/api/mcp') ||
               str_contains(strtolower($server->name), 'built-in');
    }

    /**
     * Query tools available on an MCP Server and update cache.
     */
    public function syncTools(McpServer $server): array
    {
        // If built-in server and internal bypass or fallback
        if ($this->isBuiltInServer($server)) {
            $tools = $this->builtInTools->getToolsDeclaration();
            $server->update(['tools_cache' => $tools]);

            return [
                'success' => true,
                'count' => count($tools),
                'tools' => $tools,
            ];
        }

        try {
            $headers = $this->getHeaders($server);

            // Send MCP JSON-RPC tools/list request
            $payload = [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
                'params' => (object) [],
            ];

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($server->endpoint_url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $tools = $data['result']['tools'] ?? $data['tools'] ?? [];

                $server->update(['tools_cache' => $tools]);

                return [
                    'success' => true,
                    'count' => count($tools),
                    'tools' => $tools,
                ];
            }

            return [
                'success' => false,
                'error' => 'HTTP Status '.$response->status().': '.$response->body(),
            ];
        } catch (Exception $e) {
            Log::warning("MCP sync tools failed for {$server->name}: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect all tools from all active MCP servers formatted for LLM function calling.
     */
    public function getAllActiveTools(): array
    {
        $servers = McpServer::where('is_active', true)->get();
        $formattedTools = [];

        foreach ($servers as $server) {
            $tools = $server->tools_cache ?? [];
            if (! is_array($tools)) {
                continue;
            }

            foreach ($tools as $tool) {
                if (empty($tool['name'])) {
                    continue;
                }

                $parameters = $tool['inputSchema'] ?? $tool['parameters'] ?? [
                    'type' => 'object',
                    'properties' => (object) [],
                ];

                $formattedTools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => ($tool['description'] ?? 'MCP tool')." (Provided by {$server->name})",
                        'parameters' => $parameters,
                    ],
                    'server_id' => $server->id,
                ];
            }
        }

        return $formattedTools;
    }

    /**
     * Execute a tool call against the server that owns the tool.
     */
    public function executeTool(string $toolName, array $arguments): array
    {
        $servers = McpServer::where('is_active', true)->get();
        $targetServer = null;

        foreach ($servers as $server) {
            $tools = $server->tools_cache ?? [];
            if (is_array($tools)) {
                foreach ($tools as $t) {
                    if (($t['name'] ?? '') === $toolName) {
                        $targetServer = $server;
                        break 2;
                    }
                }
            }
        }

        if (! $targetServer) {
            return [
                'success' => false,
                'output' => "Error: Tool '{$toolName}' not found on any active MCP server.",
            ];
        }

        // Direct execution for built-in MCP server tools
        if ($this->isBuiltInServer($targetServer)) {
            return $this->builtInTools->execute($toolName, $arguments);
        }

        try {
            $headers = $this->getHeaders($targetServer);

            $payload = [
                'jsonrpc' => '2.0',
                'id' => rand(100, 9999),
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => (object) $arguments,
                ],
            ];

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($targetServer->endpoint_url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data['result'] ?? $data;
                $content = $result['content'] ?? $result;

                return [
                    'success' => true,
                    'output' => is_string($content) ? $content : json_encode($content, JSON_PRETTY_PRINT),
                ];
            }

            return [
                'success' => false,
                'output' => "MCP server returned HTTP {$response->status()}: {$response->body()}",
            ];
        } catch (Exception $e) {
            Log::error("MCP tool execution error ({$toolName}): ".$e->getMessage());

            return [
                'success' => false,
                'output' => 'Connection error executing MCP tool: '.$e->getMessage(),
            ];
        }
    }
}
