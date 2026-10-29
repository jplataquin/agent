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
     * Check if the MCP server endpoint refers specifically to our internal built-in server.
     * Prevents hijacking external servers that share an /api/mcp path or similar names.
     */
    protected function isBuiltInServer(McpServer $server): bool
    {
        $url = trim($server->endpoint_url);

        // Explicit relative path on current app
        if ($url === '/api/mcp') {
            return true;
        }

        $appUrl = config('app.url', 'http://localhost:8000');
        $builtInFullUrl = rtrim($appUrl, '/').'/api/mcp';
        if ($url === $builtInFullUrl) {
            return true;
        }

        $parsedUrl = parse_url($url);
        $path = rtrim($parsedUrl['path'] ?? '', '/');

        // Path must strictly match /api/mcp
        if ($path !== '/api/mcp') {
            return false;
        }

        $parsedAppUrl = parse_url($appUrl);
        $appHost = strtolower($parsedAppUrl['host'] ?? 'localhost');
        $appPort = $parsedAppUrl['port'] ?? null;

        $host = strtolower($parsedUrl['host'] ?? '');
        $port = $parsedUrl['port'] ?? null;

        // In web context, match against current request host and port
        if (! app()->runningInConsole() && request()->getHost()) {
            $currentHost = strtolower(request()->getHost());
            $currentPort = request()->getPort();
            if ($host === $currentHost && ($port === null || $port === $currentPort)) {
                return true;
            }
        }

        // Match against configured app.url localhost
        if (in_array($host, [$appHost, 'localhost', '127.0.0.1']) && in_array($appHost, ['localhost', '127.0.0.1'])) {
            if ($port === $appPort) {
                return true;
            }
        }

        return false;
    }

    /**
     * Query tools available on an MCP Server and update cache and sync state.
     */
    public function syncTools(McpServer $server): array
    {
        // Internal Built-in Server Bypass (Only for THIS application instance)
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
        $url = trim($server->endpoint_url);

        // 1. If endpoint points to an SSE route (e.g. ends with /sse)
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (str_ends_with(strtolower($path), '/sse')) {
            $sseResult = $this->discoverViaSse($server, $url);
            if ($sseResult['success'] && ! empty($sseResult['tools'])) {
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
            if ($retryResult['success'] && ! empty($retryResult['tools'])) {
                return $retryResult;
            }
        }

        // 4. Try candidate paths if URL has no specific path or returned 404/405
        $candidates = [];
        if (empty($path) || $path === '/') {
            $candidates = [
                rtrim($url, '/').'/mcp',
                rtrim($url, '/').'/rpc',
                rtrim($url, '/').'/api/mcp',
                rtrim($url, '/').'/sse',
            ];
        } elseif (! str_ends_with($url, '/sse')) {
            $candidates = [
                rtrim($url, '/').'/sse',
            ];
        }

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

            if ($candidateRes['success'] && ! empty($candidateRes['tools'])) {
                return $candidateRes;
            }
        }

        // If direct post succeeded (even with 0 tools), return it
        if ($postResult['success']) {
            return $postResult;
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
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }

        $parsed = parse_url($sseUrl);
        $baseUrl = ($parsed['scheme'] ?? 'http').'://'.($parsed['host'] ?? 'localhost').(isset($parsed['port']) ? ':'.$parsed['port'] : '');

        $messageEndpoint = null;
        $sseBuffer = '';

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $sseUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $chunk) use (&$sseBuffer, &$messageEndpoint) {
                $sseBuffer .= $chunk;
                $lines = explode("\n", $sseBuffer);
                $isEndpointEvent = false;

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'event: endpoint')) {
                        $isEndpointEvent = true;
                    } elseif ($isEndpointEvent && str_starts_with($line, 'data:')) {
                        $endpointPart = trim(substr($line, 5));
                        if (! empty($endpointPart)) {
                            $messageEndpoint = $endpointPart;

                            return 0; // Abort transfer immediately
                        }
                    } elseif (str_starts_with($line, 'data:') && (str_contains($line, 'message') || str_contains($line, 'sessionId'))) {
                        $endpointPart = trim(substr($line, 5));
                        if (! empty($endpointPart) && (str_starts_with($endpointPart, '/') || str_starts_with($endpointPart, 'http'))) {
                            $messageEndpoint = $endpointPart;

                            return 0;
                        }
                    }
                }

                if (strlen($sseBuffer) > 16384) {
                    return 0;
                }

                return strlen($chunk);
            });

            curl_exec($ch);
            curl_close($ch);
        }

        // Fallback if curl writefunction didn't find endpoint
        if (! $messageEndpoint) {
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders($headers)
                    ->timeout(5)
                    ->get($sseUrl);

                if ($response->successful()) {
                    $body = $response->body();
                    $lines = explode("\n", $body);
                    $isEndpointEvent = false;

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (str_starts_with($line, 'event: endpoint')) {
                            $isEndpointEvent = true;
                        } elseif ($isEndpointEvent && str_starts_with($line, 'data:')) {
                            $endpointPart = trim(substr($line, 5));
                            if (! empty($endpointPart)) {
                                $messageEndpoint = $endpointPart;
                                break;
                            }
                        } elseif (str_starts_with($line, 'data:') && str_contains($line, 'messages')) {
                            $endpointPart = trim(substr($line, 5));
                            if (! empty($endpointPart)) {
                                $messageEndpoint = $endpointPart;
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                Log::debug('MCP SSE fallback discovery error: '.$e->getMessage());
            }
        }

        if ($messageEndpoint) {
            if (str_starts_with($messageEndpoint, '/')) {
                $messageEndpoint = $baseUrl.$messageEndpoint;
            }

            $jsonHeaders = $this->getHeaders($server);
            $jsonHeaders['Accept'] = 'application/json';

            $this->performInitializeHandshake($messageEndpoint, $jsonHeaders);

            return $this->queryToolsList($messageEndpoint, $jsonHeaders);
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
            } elseif (isset($data['data']['tools']) && is_array($data['data']['tools'])) {
                $rawTools = $data['data']['tools'];
            } elseif (isset($data['data']) && is_array($data['data']) && array_is_list($data['data'])) {
                $rawTools = $data['data'];
            } elseif (isset($data['result']['data']) && is_array($data['result']['data'])) {
                $rawTools = $data['result']['data'];
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

            // Support inputSchema, input_schema, parameters, schema, args
            $schema = $tool['inputSchema']
                ?? $tool['input_schema']
                ?? $tool['parameters']
                ?? $tool['schema']
                ?? [
                    'type' => 'object',
                    'properties' => (object) [],
                ];

            if (! is_array($schema) || empty($schema)) {
                $schema = [
                    'type' => 'object',
                    'properties' => (object) [],
                ];
            } else {
                if (! isset($schema['type'])) {
                    $schema['type'] = 'object';
                }
                if (! isset($schema['properties']) || (is_array($schema['properties']) && empty($schema['properties']))) {
                    $schema['properties'] = (object) [];
                }
            }

            $normalized[] = [
                'name' => trim($name),
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

                $parameters = $tool['inputSchema']
                    ?? $tool['input_schema']
                    ?? $tool['parameters']
                    ?? $tool['schema']
                    ?? [
                        'type' => 'object',
                        'properties' => (object) [],
                    ];

                if (! is_array($parameters) || empty($parameters)) {
                    $parameters = [
                        'type' => 'object',
                        'properties' => (object) [],
                    ];
                } else {
                    if (! isset($parameters['type'])) {
                        $parameters['type'] = 'object';
                    }
                    if (! isset($parameters['properties']) || (is_array($parameters['properties']) && empty($parameters['properties']))) {
                        $parameters['properties'] = (object) [];
                    }
                }

                $isInternal = $this->isBuiltInServer($server);
                $typeLabel = $isInternal ? 'Internal tool' : 'MCP tool';
                $formattedTools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => ($tool['description'] ?? $typeLabel)." (Provided by {$server->name})",
                        'parameters' => $parameters,
                    ],
                    'server_id' => $server->id,
                    'is_internal' => $isInternal,
                ];
            }
        }

        return $formattedTools;
    }

    /**
     * Get a breakdown of active tools separated by internal tools and external MCP tools.
     *
     * @return array{internal: int, external_mcp: int, total: int}
     */
    public function getActiveToolsBreakdown(): array
    {
        $servers = McpServer::where('is_active', true)->get();
        $internalCount = 0;
        $externalMcpCount = 0;

        foreach ($servers as $server) {
            $tools = $server->tools_cache ?? [];
            if (! is_array($tools)) {
                continue;
            }

            $count = count(array_filter($tools, fn ($t) => ! empty($t['name'])));
            if ($this->isBuiltInServer($server)) {
                $internalCount += $count;
            } else {
                $externalMcpCount += $count;
            }
        }

        // Fallback: If no internal tools server was registered in mcp_servers table yet,
        // count the declared native internal tools from BuiltInToolsService
        if ($internalCount === 0) {
            $internalCount = count($this->builtInTools->getToolsDeclaration());
        }

        return [
            'internal' => $internalCount,
            'external_mcp' => $externalMcpCount,
            'total' => $internalCount + $externalMcpCount,
        ];
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
                'output' => "Error: Tool '{$toolName}' not found on any active server.",
            ];
        }

        // Direct execution for native internal tools
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

                // If error indicates uninitialized, handshake and retry once
                if (is_array($data) && isset($data['error'])) {
                    $errCode = $data['error']['code'] ?? 0;
                    $errMsg = $data['error']['message'] ?? '';
                    if ($errCode === -32002 || str_contains(strtolower($errMsg), 'not initialized')) {
                        $this->performInitializeHandshake($targetServer->endpoint_url, $headers);
                        $retry = Http::withoutVerifying()
                            ->withHeaders($headers)
                            ->timeout(30)
                            ->post($targetServer->endpoint_url, $payload);
                        if ($retry->successful()) {
                            $data = $retry->json();
                        }
                    }
                }

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
