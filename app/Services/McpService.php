<?php

namespace App\Services;

use App\Models\McpServer;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    public function getHeaders(McpServer $server): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream, */*',
        ];

        if ($server->auth_type === 'bearer' && ! empty($server->auth_token)) {
            $token = trim($server->auth_token);
            if (str_starts_with(strtolower($token), 'bearer ')) {
                $token = substr($token, 7);
            }
            $headers['Authorization'] = 'Bearer '.$token;
        } elseif ($server->auth_type === 'custom_header' && ! empty($server->auth_token)) {
            $headerName = $server->custom_header_name ?: 'X-API-Key';
            $headers[$headerName] = trim($server->auth_token);
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
     * Query tools available on an MCP Server and update cache and sync state.
     */
    public function syncTools(McpServer $server): array
    {
        // Internal Built-in Server Bypass
        if ($this->isBuiltInServer($server)) {
            $tools = $this->builtInTools->getToolsDeclaration();
            $server->update([
                'tools_cache' => $tools,
                'last_sync_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_error' => null,
            ]);

            return [
                'success' => true,
                'count' => count($tools),
                'tools' => $tools,
            ];
        }

        try {
            $result = $this->discoverToolsFromEndpoint($server);

            if ($result['success']) {
                $server->update([
                    'tools_cache' => $result['tools'],
                    'last_sync_at' => now(),
                    'last_sync_status' => 'success',
                    'last_sync_error' => null,
                ]);

                return $result;
            }

            $server->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_error' => $result['error'] ?? 'Discovery failed.',
            ]);

            return $result;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            Log::warning("MCP sync tools failed for {$server->name}: ".$msg);

            $server->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_error' => $msg,
            ]);

            return [
                'success' => false,
                'error' => $msg,
            ];
        }
    }

    /**
     * Attempt multi-strategy discovery of tools from the MCP server endpoint.
     */
    protected function discoverToolsFromEndpoint(McpServer $server): array
    {
        $headers = $this->getHeaders($server);
        $url = $server->endpoint_url;

        // 1. If endpoint points to an SSE route (e.g. ends with /sse)
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (str_ends_with(strtolower($path), '/sse')) {
            $sseResult = $this->discoverViaSse($server, $url);
            if ($sseResult['success']) {
                return $sseResult;
            }
        }

        // 2. Direct tools/list JSON-RPC call
        $postResult = $this->queryToolsList($url, $headers);
        if ($postResult['success'] && ! empty($postResult['tools'])) {
            return $postResult;
        }

        // 3. If tools/list failed (e.g. uninitialized, method not allowed), attempt initialize handshake
        $initResult = $this->performInitializeHandshake($url, $headers);
        if ($initResult['success']) {
            $retryResult = $this->queryToolsList($url, $headers);
            if ($retryResult['success']) {
                return $retryResult;
            }
        }

        // If direct post succeeded with 0 tools and no error, return it
        if ($postResult['success']) {
            return $postResult;
        }

        // 4. Try candidate paths if URL has no specific path
        if (empty($path) || $path === '/') {
            $candidates = [
                rtrim($url, '/').'/mcp',
                rtrim($url, '/').'/rpc',
                rtrim($url, '/').'/api/mcp',
                rtrim($url, '/').'/sse',
            ];

            foreach ($candidates as $candidate) {
                if (str_ends_with($candidate, '/sse')) {
                    $candidateRes = $this->discoverViaSse($server, $candidate);
                } else {
                    $candidateRes = $this->queryToolsList($candidate, $headers);
                    if (! $candidateRes['success']) {
                        $this->performInitializeHandshake($candidate, $headers);
                        $candidateRes = $this->queryToolsList($candidate, $headers);
                    }
                }

                if ($candidateRes['success']) {
                    return $candidateRes;
                }
            }
        }

        // 5. Fallback: REST GET /tools or GET /
        $restResult = $this->queryRestTools($url, $headers);
        if ($restResult['success'] && ! empty($restResult['tools'])) {
            return $restResult;
        }

        return $postResult;
    }

    /**
     * Send JSON-RPC tools/list request and parse tools.
     */
    protected function queryToolsList(string $url, array $headers): array
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(12)
                ->post($url, [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'tools/list',
                    'params' => (object) [],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Explicit JSON-RPC error response check
                if (is_array($data) && isset($data['error'])) {
                    $err = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : (string) $data['error'];

                    return [
                        'success' => false,
                        'error' => "MCP JSON-RPC Error: {$err}",
                    ];
                }

                $tools = $this->extractToolsFromResponse($data);

                return [
                    'success' => true,
                    'count' => count($tools),
                    'tools' => $tools,
                ];
            }

            return [
                'success' => false,
                'error' => 'HTTP Status '.$response->status().': '.Str::limit($response->body(), 160),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Perform the standard MCP initialize handshake.
     */
    protected function performInitializeHandshake(string $url, array $headers): array
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(10)
                ->post($url, [
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'method' => 'initialize',
                    'params' => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => (object) [],
                        'clientInfo' => [
                            'name' => 'company-ai-agent',
                            'version' => '1.0.0',
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (! isset($data['error'])) {
                    // Send notifications/initialized notification
                    Http::withoutVerifying()
                        ->withHeaders($headers)
                        ->timeout(5)
                        ->post($url, [
                            'jsonrpc' => '2.0',
                            'method' => 'notifications/initialized',
                        ]);

                    return ['success' => true];
                }
            }
        } catch (Exception $e) {
            // Handshake failed
        }

        return ['success' => false];
    }

    /**
     * Discover tools through MCP SSE transport.
     */
    protected function discoverViaSse(McpServer $server, string $sseUrl): array
    {
        $headers = $this->getHeaders($server);
        $headers['Accept'] = 'text/event-stream';

        try {
            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(6)
                ->get($sseUrl);

            if ($response->successful()) {
                $body = $response->body();
                $messageEndpoint = null;
                $lines = explode("\n", $body);
                $isEndpointEvent = false;

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'event: endpoint')) {
                        $isEndpointEvent = true;
                    } elseif ($isEndpointEvent && str_starts_with($line, 'data:')) {
                        $dataPart = trim(substr($line, 5));
                        if (! empty($dataPart)) {
                            $messageEndpoint = $dataPart;
                            break;
                        }
                    } elseif (str_starts_with($line, 'data:') && str_contains($line, 'messages')) {
                        $dataPart = trim(substr($line, 5));
                        if (! empty($dataPart)) {
                            $messageEndpoint = $dataPart;
                            break;
                        }
                    }
                }

                if ($messageEndpoint) {
                    // Resolve relative URI to absolute URL
                    if (str_starts_with($messageEndpoint, '/')) {
                        $parsed = parse_url($sseUrl);
                        $base = ($parsed['scheme'] ?? 'http').'://'.($parsed['host'] ?? 'localhost').(isset($parsed['port']) ? ':'.$parsed['port'] : '');
                        $messageEndpoint = $base.$messageEndpoint;
                    }

                    $headers['Accept'] = 'application/json';
                    $this->performInitializeHandshake($messageEndpoint, $headers);

                    return $this->queryToolsList($messageEndpoint, $headers);
                }
            }
        } catch (Exception $e) {
            Log::debug('MCP SSE discovery error: '.$e->getMessage());
        }

        return ['success' => false, 'error' => 'Could not negotiate MCP SSE transport.'];
    }

    /**
     * Fallback discovery via REST GET endpoints.
     */
    protected function queryRestTools(string $url, array $headers): array
    {
        try {
            $urlsToTry = [$url, rtrim($url, '/').'/tools'];
            foreach ($urlsToTry as $u) {
                $response = Http::withoutVerifying()
                    ->withHeaders($headers)
                    ->timeout(8)
                    ->get($u);

                if ($response->successful()) {
                    $data = $response->json();
                    $tools = $this->extractToolsFromResponse($data);
                    if (! empty($tools)) {
                        return [
                            'success' => true,
                            'count' => count($tools),
                            'tools' => $tools,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // pass
        }

        return ['success' => false, 'error' => 'No tools returned from endpoint.'];
    }

    /**
     * Extract and normalize tools array from various response formats.
     */
    protected function extractToolsFromResponse(mixed $data): array
    {
        $rawTools = [];

        if (is_array($data)) {
            if (isset($data['result']['tools']) && is_array($data['result']['tools'])) {
                $rawTools = $data['result']['tools'];
            } elseif (isset($data['tools']) && is_array($data['tools'])) {
                $rawTools = $data['tools'];
            } elseif (isset($data['result']) && is_array($data['result']) && array_is_list($data['result'])) {
                $rawTools = $data['result'];
            } elseif (array_is_list($data)) {
                $rawTools = $data;
            }
        }

        $normalized = [];
        foreach ($rawTools as $tool) {
            if (! is_array($tool)) {
                continue;
            }
            if (isset($tool['function']) && is_array($tool['function'])) {
                $tool = $tool['function'];
            }
            $name = $tool['name'] ?? null;
            if (empty($name) || ! is_string($name)) {
                continue;
            }

            $schema = $tool['inputSchema'] ?? $tool['parameters'] ?? [
                'type' => 'object',
                'properties' => (object) [],
            ];

            $normalized[] = [
                'name' => $name,
                'description' => $tool['description'] ?? 'MCP tool provided by server',
                'inputSchema' => $schema,
            ];
        }

        return $normalized;
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

            $response = Http::withoutVerifying()
                ->withHeaders($headers)
                ->timeout(30)
                ->post($targetServer->endpoint_url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (is_array($data) && isset($data['error'])) {
                    $err = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : (string) $data['error'];

                    return [
                        'success' => false,
                        'output' => "MCP tool error: {$err}",
                    ];
                }

                $result = $data['result'] ?? $data;
                $content = $result['content'] ?? $result;

                if (is_array($content)) {
                    $texts = [];
                    foreach ($content as $item) {
                        if (is_array($item) && isset($item['text'])) {
                            $texts[] = $item['text'];
                        } elseif (is_string($item)) {
                            $texts[] = $item;
                        }
                    }
                    $output = ! empty($texts) ? implode("\n", $texts) : json_encode($content, JSON_PRETTY_PRINT);
                } else {
                    $output = (string) $content;
                }

                return [
                    'success' => true,
                    'output' => $output,
                ];
            }

            return [
                'success' => false,
                'output' => "MCP server returned HTTP {$response->status()}: ".Str::limit($response->body(), 200),
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
