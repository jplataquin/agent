<?php

namespace App\Console\Commands;

use App\Models\McpServer;
use App\Services\McpService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('mcp:register-playwright {--port=3030 : Port number where the Playwright MCP server is listening} {--host=http://localhost : Host base URL for the server} {--name=Playwright Headless Browser MCP : Display name for the MCP server} {--sync : Automatically ping and sync tools from the server upon registration}')]
#[Description('Register or update the local Playwright browser MCP server in the database')]
class RegisterPlaywrightMcpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:register-playwright {--port=3030 : Port number where the Playwright MCP server is listening} {--host=http://localhost : Host base URL for the server} {--name=Playwright Headless Browser MCP : Display name for the MCP server} {--sync : Automatically ping and sync tools from the server upon registration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register or update the local Playwright browser MCP server in the database';

    /**
     * Execute the console command.
     */
    public function handle(McpService $mcpService): int
    {
        $port = (int) $this->option('port');
        $host = rtrim((string) $this->option('host'), '/');
        $name = (string) $this->option('name');
        $endpoint = "{$host}:{$port}/mcp";
        $healthUrl = "{$host}:{$port}/health";

        $this->info('=== Register Playwright Browser MCP Server ===');
        $this->line("Target Endpoint: <comment>{$endpoint}</comment>");

        $toolsFallback = [
            [
                'name' => 'browser_navigate',
                'description' => 'Navigate the headless browser to a specified URL, waiting for page load, and return the page title, URL, interactive elements, and content.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The target website URL to navigate to (e.g. https://news.ycombinator.com)',
                        ],
                        'wait_until' => [
                            'type' => 'string',
                            'description' => 'When to consider navigation succeeded: "load", "domcontentloaded", or "networkidle"',
                            'enum' => ['load', 'domcontentloaded', 'networkidle'],
                        ],
                        'timeout' => [
                            'type' => 'integer',
                            'description' => 'Timeout in milliseconds (default: 30000)',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
            [
                'name' => 'browser_click',
                'description' => 'Click an element or button on the page using a CSS selector or Playwright text locator.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'selector' => [
                            'type' => 'string',
                            'description' => 'CSS selector or text locator of the element to click',
                        ],
                        'wait_after' => [
                            'type' => 'integer',
                            'description' => 'Milliseconds to wait after click for DOM or network response',
                        ],
                    ],
                    'required' => ['selector'],
                ],
            ],
            [
                'name' => 'browser_type',
                'description' => 'Fill or type text into an input field or textarea on the active page.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'selector' => [
                            'type' => 'string',
                            'description' => 'Selector of the input element',
                        ],
                        'text' => [
                            'type' => 'string',
                            'description' => 'The text string to type into the field',
                        ],
                        'press_enter' => [
                            'type' => 'boolean',
                            'description' => 'Whether to press Enter key immediately after typing',
                        ],
                    ],
                    'required' => ['selector', 'text'],
                ],
            ],
            [
                'name' => 'browser_press_key',
                'description' => 'Press a keyboard key on the active page (e.g. "Enter", "Tab", "Escape").',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'Key name to press (e.g. "Enter", "Escape", "Tab")',
                        ],
                    ],
                    'required' => ['key'],
                ],
            ],
            [
                'name' => 'browser_snapshot',
                'description' => 'Get a fresh snapshot of the active page including title, URL, visible interactive elements, and content.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'max_length' => [
                            'type' => 'integer',
                            'description' => 'Maximum characters of body text to return',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'browser_evaluate',
                'description' => 'Safely execute a JavaScript expression in the browser page context and return the result as JSON.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'JavaScript expression to evaluate',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ],
            [
                'name' => 'browser_reset',
                'description' => 'Reset the browser session, closing the current page and clearing all cookies and navigation state.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];

        // Register or update server in database
        $server = McpServer::updateOrCreate(
            ['name' => $name],
            [
                'endpoint_url' => $endpoint,
                'auth_type' => 'none',
                'auth_token' => null,
                'description' => 'Stateful Playwright browser MCP server for interactive web browsing, clicking, typing, and page inspection.',
                'tools_cache' => $toolsFallback,
                'is_active' => true,
            ]
        );

        $this->info("Server record updated: <comment>{$server->name}</comment> (ID: {$server->id})");

        // Sync if requested
        if ($this->option('sync')) {
            $this->line("Checking health and syncing tools from {$healthUrl}...");
            try {
                $health = Http::timeout(4)->get($healthUrl);
                if ($health->successful()) {
                    $this->info('Playwright Server is reachable! Running syncTools...');
                    $sync = $mcpService->syncTools($server);
                    if ($sync['success']) {
                        $this->info("Successfully synced {$sync['count']} tools directly from the live server!");
                    } else {
                        $this->warn('Sync attempted but returned error: '.($sync['error'] ?? 'Unknown error'));
                    }
                } else {
                    $this->warn("Playwright server returned HTTP {$health->status()} on health check.");
                }
            } catch (\Exception $e) {
                $this->warn("Could not reach live server ({$e->getMessage()}). Using pre-configured tool declarations.");
            }
        }

        $server->refresh();

        $this->newLine();
        $this->table(
            ['ID', 'Name', 'Endpoint', 'Active', 'Tools Count', 'Last Sync Status'],
            [
                [
                    $server->id,
                    $server->name,
                    $server->endpoint_url,
                    $server->is_active ? 'Yes' : 'No',
                    is_array($server->tools_cache) ? count($server->tools_cache) : 0,
                    $server->last_sync_status ?? 'pre-seeded',
                ],
            ]
        );

        $this->info('Playwright Browser MCP server registered successfully!');

        return self::SUCCESS;
    }
}
