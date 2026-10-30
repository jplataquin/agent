import express from 'express';
import fs from 'fs';
import path from 'path';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';

const app = express();
const PORT = process.env.PORT || 3030;
const HOST = process.env.HOST || '0.0.0.0';

app.use(express.json({ limit: '10mb' }));

let mcpClient = null;
let mcpTransport = null;
let toolsCache = [];
let isConnecting = false;

/**
 * Initialize or get active MCP Client connected to official @playwright/mcp via StdioClientTransport.
 */
async function getMcpClient() {
    if (mcpClient) {
        return mcpClient;
    }

    if (isConnecting) {
        while (isConnecting) {
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        if (mcpClient) return mcpClient;
    }

    isConnecting = true;
    try {
        const browserType = process.env.PLAYWRIGHT_BROWSER || 'chromium';
        const runtimeDir = process.env.PLAYWRIGHT_RUNTIME_DIR || '/tmp/playwright-agent-runtime';
        const crashDumpsDir = path.join(runtimeDir, 'crash_dumps');
        const configDir = path.join(runtimeDir, '.config');
        const cacheDir = path.join(runtimeDir, '.cache');
        const userDataDir = path.join(runtimeDir, 'user-data');

        // Ensure writable directories for www-data or containerized environments
        fs.mkdirSync(runtimeDir, { recursive: true });
        fs.mkdirSync(crashDumpsDir, { recursive: true });
        fs.mkdirSync(configDir, { recursive: true });
        fs.mkdirSync(cacheDir, { recursive: true });
        fs.mkdirSync(userDataDir, { recursive: true });

        const configPath = path.join(runtimeDir, 'mcp-config.json');

        const config = {
            browser: {
                browserName: browserType,
                isolated: true,
                launchOptions: {
                    headless: true,
                    args: [
                        '--no-sandbox',
                        '--disable-setuid-sandbox',
                        '--disable-dev-shm-usage',
                        '--disable-gpu',
                        '--disable-crash-reporter',
                        '--disable-breakpad',
                        '--disable-crashpad',
                        `--crash-dumps-dir=${crashDumpsDir}`,
                        `--user-data-dir=${userDataDir}`,
                        '--disable-software-rasterizer',
                    ],
                },
            },
        };
        fs.writeFileSync(configPath, JSON.stringify(config, null, 2));

        const args = [
            'playwright-mcp',
            '--config', configPath,
            '--allowed-hosts', '*',
        ];

        console.log(`[Playwright MCP Bridge] Spawning official @playwright/mcp with Linux VPS stability config...`);

        mcpTransport = new StdioClientTransport({
            command: 'npx',
            args: args,
            env: {
                ...process.env,
                HOME: runtimeDir,
                XDG_CONFIG_HOME: configDir,
                XDG_CACHE_HOME: cacheDir,
                TMPDIR: runtimeDir,
                PLAYWRIGHT_BROWSERS_PATH: process.env.PLAYWRIGHT_BROWSERS_PATH || '/var/www/.cache/ms-playwright',
            },
        });

        const client = new Client(
            {
                name: 'playwright-http-bridge',
                version: '2.0.0',
            },
            {
                capabilities: {},
            }
        );

        await client.connect(mcpTransport);
        console.log('[Playwright MCP Bridge] Successfully connected to official @playwright/mcp via stdio.');

        mcpClient = client;

        // Fetch and cache tools
        try {
            const listRes = await mcpClient.listTools();
            toolsCache = listRes.tools || [];
            console.log(`[Playwright MCP Bridge] Discovered ${toolsCache.length} tools from official server.`);
        } catch (e) {
            console.warn('[Playwright MCP Bridge] Failed to pre-fetch tools list:', e.message);
        }

        mcpTransport.onclose = () => {
            console.warn('[Playwright MCP Bridge] Underlying stdio transport closed.');
            mcpClient = null;
            mcpTransport = null;
        };

        mcpTransport.onerror = (err) => {
            console.error('[Playwright MCP Bridge] Stdio transport error:', err);
        };

        return mcpClient;
    } finally {
        isConnecting = false;
    }
}

/**
 * Reset / Restart the MCP Client process.
 */
async function resetMcpClient() {
    try {
        if (mcpClient) {
            try {
                await mcpClient.callTool({ name: 'browser_close', arguments: {} }).catch(() => {});
            } catch (_) {}
            await mcpClient.close().catch(() => {});
        }
    } finally {
        mcpClient = null;
        mcpTransport = null;
    }
}

// Backwards-compatible tools definition for reset
const RESET_TOOL = {
    name: 'browser_reset',
    description: 'Reset the browser session, closing all active pages and tabs.',
    inputSchema: {
        type: 'object',
        properties: {},
        required: [],
    },
};

// Health Check Endpoint
app.get(['/', '/health'], async (req, res) => {
    let clientConnected = false;
    let toolCount = toolsCache.length;

    try {
        const client = await getMcpClient();
        clientConnected = Boolean(client);
        if (toolsCache.length === 0 && client) {
            const listRes = await client.listTools();
            toolsCache = listRes.tools || [];
            toolCount = toolsCache.length;
        }
    } catch (e) {
        clientConnected = false;
    }

    res.json({
        status: clientConnected ? 'ok' : 'degraded',
        server: 'playwright-mcp-server',
        version: '2.0.0',
        engine: 'official @playwright/mcp',
        connected: clientConnected,
        tools_count: toolCount + 1, // + browser_reset
    });
});

// MCP JSON-RPC 2.0 Handler
app.post(['/', '/mcp', '/rpc', '/api/mcp'], async (req, res) => {
    const { jsonrpc, id, method, params } = req.body || {};

    if (method === 'initialize') {
        return res.json({
            jsonrpc: '2.0',
            id: id ?? 1,
            result: {
                protocolVersion: '2024-11-05',
                serverInfo: {
                    name: 'playwright-official-mcp-bridge',
                    version: '2.0.0',
                },
                capabilities: {
                    tools: {},
                },
            },
        });
    }

    if (method === 'tools/list') {
        try {
            const client = await getMcpClient();
            const listRes = await client.listTools();
            const tools = listRes.tools || [];
            toolsCache = tools;

            // Merge in browser_reset if not present
            const allTools = [...tools];
            if (!allTools.some((t) => t.name === 'browser_reset')) {
                allTools.push(RESET_TOOL);
            }

            return res.json({
                jsonrpc: '2.0',
                id: id ?? 1,
                result: {
                    tools: allTools,
                },
            });
        } catch (err) {
            return res.status(500).json({
                jsonrpc: '2.0',
                id: id ?? 1,
                error: {
                    code: -32603,
                    message: `Failed to list tools: ${err.message}`,
                },
            });
        }
    }

    if (method === 'tools/call') {
        const toolName = params?.name;
        const args = params?.arguments || {};

        try {
            if (toolName === 'browser_reset') {
                await resetMcpClient();
                return res.json({
                    jsonrpc: '2.0',
                    id: id ?? 1,
                    result: {
                        content: [
                            {
                                type: 'text',
                                text: 'Browser context and pages have been reset to a clean state.',
                            },
                        ],
                    },
                });
            }

            const client = await getMcpClient();
            const result = await client.callTool({
                name: toolName,
                arguments: args,
            });

            return res.json({
                jsonrpc: '2.0',
                id: id ?? 1,
                result: {
                    content: result.content || [],
                    isError: Boolean(result.isError),
                },
            });
        } catch (err) {
            return res.json({
                jsonrpc: '2.0',
                id: id ?? 1,
                result: {
                    content: [
                        {
                            type: 'text',
                            text: `Playwright execution error: ${err.message}`,
                        },
                    ],
                    isError: true,
                },
            });
        }
    }

    return res.status(404).json({
        jsonrpc: '2.0',
        id: id ?? 1,
        error: {
            code: -32601,
            message: `Method "${method}" not found.`,
        },
    });
});

// Graceful termination
const shutdown = async () => {
    try {
        await resetMcpClient();
    } finally {
        process.exit(0);
    }
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

const server = app.listen(PORT, HOST, () => {
    console.log(`[Playwright MCP Bridge] Server listening on http://${HOST}:${PORT}`);
    console.log(`[Playwright MCP Bridge] Endpoint: http://${HOST}:${PORT}/mcp`);
});

export default app;
