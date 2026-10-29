<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Service managing native internal tools (web fetch, web search, internal directory lookup, Python script execution).
 */
class BuiltInToolsService
{
    /**
     * Get the tool declarations for all native internal tools.
     */
    public function getToolsDeclaration(): array
    {
        return [
            [
                'name' => 'web_fetch',
                'description' => 'Fetch and extract clean readable text content from any public webpage or URL.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The complete HTTP or HTTPS URL to fetch (e.g. https://example.com/article)',
                        ],
                        'max_length' => [
                            'type' => 'integer',
                            'description' => 'Maximum characters of text to return (default: 4000)',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
            [
                'name' => 'web_search',
                'description' => 'Search the live web for articles, documentation, or news using keywords.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query or keywords',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'internal_company_directory',
                'description' => 'Look up company employees, departments, roles, and email addresses.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Employee name, email, or role keyword',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'execute_python_script',
                'description' => 'Execute a Python script for mathematical computations, statistical calculations, complex data manipulation, or string processing. The script should print its results to stdout.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'script' => [
                            'type' => 'string',
                            'description' => 'The Python code to execute. Must use print() to output final results.',
                        ],
                    ],
                    'required' => ['script'],
                ],
            ],
        ];
    }

    /**
     * Execute a native internal tool.
     */
    public function execute(string $name, array $arguments): array
    {
        return match ($name) {
            'web_fetch' => $this->webFetch($arguments['url'] ?? '', (int) ($arguments['max_length'] ?? 4000)),
            'web_search' => $this->webSearch($arguments['query'] ?? ''),
            'internal_company_directory' => $this->internalDirectory($arguments['query'] ?? ''),
            'execute_python_script' => $this->executePythonScript($arguments['script'] ?? ''),
            default => [
                'success' => false,
                'output' => "Unknown tool '{$name}'.",
            ],
        };
    }

    /**
     * Execute a Python script inside Docker sandbox or secured subprocess.
     */
    public function executePythonScript(string $script): array
    {
        if (trim($script) === '') {
            return [
                'success' => false,
                'output' => 'Python script code cannot be empty.',
            ];
        }

        $tempDir = storage_path('app/temp_scripts');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir.'/script_'.uniqid().'.py';
        file_put_contents($tempFile, $script);

        try {
            // Check if Docker is available on the system
            $hasDocker = false;
            try {
                $dockerCheck = new Process(['which', 'docker']);
                $dockerCheck->setTimeout(2);
                $dockerCheck->run();
                $hasDocker = $dockerCheck->isSuccessful();
            } catch (Exception $e) {
                $hasDocker = false;
            }

            if ($hasDocker) {
                $dockerImage = (string) config('services.python_sandbox.docker_image', env('PYTHON_DOCKER_IMAGE', 'python:3.15-slim'));
                if (preg_match('/^python\.(\d)/', $dockerImage)) {
                    $dockerImage = preg_replace('/^python\./', 'python:', $dockerImage);
                }

                // Secure Docker container: no network access, read-only volume, auto-remove
                $command = [
                    'docker', 'run', '--rm',
                    '--network', 'none',
                    '--memory', '256m',
                    '-v', "{$tempFile}:/app/script.py:ro",
                    $dockerImage,
                    'python', '/app/script.py',
                ];
            } else {
                // Local Python fallback
                $command = ['python3', $tempFile];
            }

            $process = new Process($command);
            $process->setTimeout(6); // 6 seconds timeout limit
            $process->run();

            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();

            if (! $process->isSuccessful()) {
                return [
                    'success' => false,
                    'output' => "Python Execution Error (exit code {$process->getExitCode()}):\n".($stderr ?: $stdout),
                ];
            }

            $output = trim($stdout);
            if ($output === '' && $stderr !== '') {
                $output = $stderr;
            } elseif ($output === '') {
                $output = '[Script executed successfully with no output. Did you forget to call print()?]';
            }

            // Cap output to 8000 characters
            if (strlen($output) > 8000) {
                $output = substr($output, 0, 8000)."\n... [Output truncated at 8000 characters]";
            }

            return [
                'success' => true,
                'output' => $output,
            ];
        } catch (ProcessTimedOutException $e) {
            return [
                'success' => false,
                'output' => 'Execution timed out after 6 seconds. Infinite loops or intensive computations are killed for safety.',
            ];
        } catch (Exception $e) {
            Log::error('Python execution exception: '.$e->getMessage());

            return [
                'success' => false,
                'output' => 'Execution error: '.$e->getMessage(),
            ];
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Fetch web page and extract clean text content.
     */
    public function webFetch(string $url, int $maxLength = 4000): array
    {
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'output' => "Invalid URL provided: '{$url}'. URL must start with http:// or https://.",
            ];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->timeout(15)->get($url);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'output' => "HTTP {$response->status()} error while fetching {$url}: ".$response->body(),
                ];
            }

            $html = $response->body();

            // Extract page title
            $title = 'No title found';
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                $title = trim(html_entity_decode($matches[1]));
            }

            // Remove scripts, styles, svg, and noisy elements
            $cleanHtml = preg_replace([
                '/<script\b[^>]*>(.*?)<\/script>/is',
                '/<style\b[^>]*>(.*?)<\/style>/is',
                '/<noscript\b[^>]*>(.*?)<\/noscript>/is',
                '/<svg\b[^>]*>(.*?)<\/svg>/is',
                '/<nav\b[^>]*>(.*?)<\/nav>/is',
                '/<footer\b[^>]*>(.*?)<\/footer>/is',
                '/<header\b[^>]*>(.*?)<\/header>/is',
            ], '', $html);

            // Convert common block tags to newlines
            $cleanHtml = preg_replace('/<\/(div|p|h1|h2|h3|h4|h5|h6|li|tr|br\s*\/?)>/i', "\n", $cleanHtml);

            // Strip remaining tags
            $text = strip_tags($cleanHtml);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Collapse whitespace
            $lines = array_filter(array_map('trim', explode("\n", $text)));
            $cleanedText = implode("\n", $lines);

            if (strlen($cleanedText) > $maxLength) {
                $cleanedText = substr($cleanedText, 0, $maxLength)."\n... [Content truncated at {$maxLength} chars]";
            }

            return [
                'success' => true,
                'output' => "Webpage Title: {$title}\nSource URL: {$url}\n\nContent:\n{$cleanedText}",
            ];
        } catch (Exception $e) {
            Log::warning("web_fetch error for {$url}: ".$e->getMessage());

            return [
                'success' => false,
                'output' => "Failed to fetch webpage ({$url}): ".$e->getMessage(),
            ];
        }
    }

    /**
     * Search the web using DuckDuckGo HTML endpoint.
     */
    public function webSearch(string $query): array
    {
        if (empty($query)) {
            return [
                'success' => false,
                'output' => 'Search query cannot be empty.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ])->timeout(12)->asForm()->post('https://html.duckduckgo.com/html/', [
                'q' => $query,
            ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'output' => "Search engine returned HTTP {$response->status()}.",
                ];
            }

            $html = $response->body();
            $results = [];

            // Match result snippets from DuckDuckGo HTML output
            if (preg_match_all('/<a class="result__snippet[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
                foreach (array_slice($matches, 0, 5) as $i => $match) {
                    $rawUrl = $match[1];
                    // Clean DuckDuckGo redirect URL
                    parse_str(parse_url($rawUrl, PHP_URL_QUERY) ?? '', $queryParams);
                    $actualUrl = $queryParams['uddg'] ?? $rawUrl;

                    $snippet = trim(strip_tags(html_entity_decode($match[2])));
                    $results[] = ($i + 1).". URL: {$actualUrl}\n   Summary: {$snippet}";
                }
            }

            if (empty($results)) {
                return [
                    'success' => true,
                    'output' => "Search query '{$query}' executed, but no direct public results were found.",
                ];
            }

            return [
                'success' => true,
                'output' => "Web Search Results for '{$query}':\n\n".implode("\n\n", $results),
            ];
        } catch (Exception $e) {
            Log::warning("web_search error for {$query}: ".$e->getMessage());

            return [
                'success' => false,
                'output' => 'Web search encountered an error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Search internal company directory.
     */
    public function internalDirectory(string $query): array
    {
        if (empty($query)) {
            return [
                'success' => false,
                'output' => 'Query cannot be empty.',
            ];
        }

        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('role', 'like', "%{$query}%")
            ->get(['id', 'name', 'email', 'role']);

        if ($users->isEmpty()) {
            return [
                'success' => true,
                'output' => "No employees or users matched query: '{$query}'.",
            ];
        }

        $lines = ["Found {$users->count()} employee(s) in company directory:"];
        foreach ($users as $u) {
            $roleLabel = $u->role === 'admin' ? 'System Administrator' : 'Employee';
            $lines[] = "- {$u->name} ({$u->email}) - Role: {$roleLabel}";
        }

        return [
            'success' => true,
            'output' => implode("\n", $lines),
        ];
    }
}
