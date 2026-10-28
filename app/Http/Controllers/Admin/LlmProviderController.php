<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LlmLog;
use App\Models\LlmProvider;
use App\Services\Llm\LlmAdapterFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LlmProviderController extends Controller
{
    public function index()
    {
        $providers = LlmProvider::latest()->paginate(15);

        return view('admin.llm_providers.index', compact('providers'));
    }

    public function create()
    {
        return view('admin.llm_providers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider_type' => 'required|in:openai,anthropic,gemini,openrouter,custom',
            'api_key' => 'required|string',
            'model' => 'required|string|max:255',
            'base_url' => 'nullable|url|max:255',
            'temperature' => 'required|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1',
            'max_tool_iterations' => 'nullable|integer|min:1',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $isDefault = $request->has('is_default');
        if ($isDefault) {
            LlmProvider::query()->update(['is_default' => false]);
        }

        $validated['is_default'] = $isDefault;
        $validated['is_active'] = $request->has('is_active');

        if ($validated['provider_type'] === 'gemini') {
            if (! empty($validated['base_url']) && str_contains($validated['base_url'], 'api.openai.com')) {
                $validated['base_url'] = null;
            }
            if ($validated['model'] === 'gpt-4o' || empty($validated['model']) || str_contains(strtolower($validated['model']), 'gpt-')) {
                $validated['model'] = 'gemini-1.5-flash';
            }
        } elseif ($validated['provider_type'] === 'anthropic') {
            if (! empty($validated['base_url']) && str_contains($validated['base_url'], 'api.openai.com')) {
                $validated['base_url'] = null;
            }
            if ($validated['model'] === 'gpt-4o' || empty($validated['model']) || str_contains(strtolower($validated['model']), 'gpt-')) {
                $validated['model'] = 'claude-3-5-sonnet-20241022';
            }
        }

        LlmProvider::create($validated);

        return redirect()->route('admin.llm-providers.index')->with('success', 'LLM Provider credentials saved.');
    }

    public function edit(LlmProvider $llmProvider)
    {
        return view('admin.llm_providers.edit', ['provider' => $llmProvider]);
    }

    public function update(Request $request, LlmProvider $llmProvider)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider_type' => 'required|in:openai,anthropic,gemini,openrouter,custom',
            'api_key' => 'required|string',
            'model' => 'required|string|max:255',
            'base_url' => 'nullable|url|max:255',
            'temperature' => 'required|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1',
            'max_tool_iterations' => 'nullable|integer|min:1',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $isDefault = $request->has('is_default');
        if ($isDefault) {
            LlmProvider::where('id', '!=', $llmProvider->id)->update(['is_default' => false]);
        }

        $validated['is_default'] = $isDefault;
        $validated['is_active'] = $request->has('is_active');

        if ($validated['provider_type'] === 'gemini') {
            if (! empty($validated['base_url']) && str_contains($validated['base_url'], 'api.openai.com')) {
                $validated['base_url'] = null;
            }
            if ($validated['model'] === 'gpt-4o' || empty($validated['model']) || str_contains(strtolower($validated['model']), 'gpt-')) {
                $validated['model'] = 'gemini-1.5-flash';
            }
        } elseif ($validated['provider_type'] === 'anthropic') {
            if (! empty($validated['base_url']) && str_contains($validated['base_url'], 'api.openai.com')) {
                $validated['base_url'] = null;
            }
            if ($validated['model'] === 'gpt-4o' || empty($validated['model']) || str_contains(strtolower($validated['model']), 'gpt-')) {
                $validated['model'] = 'claude-3-5-sonnet-20241022';
            }
        }

        $llmProvider->update($validated);

        return redirect()->route('admin.llm-providers.index')->with('success', 'LLM Provider credentials updated.');
    }

    public function setDefault(LlmProvider $llmProvider)
    {
        LlmProvider::query()->update(['is_default' => false]);
        $llmProvider->update(['is_default' => true, 'is_active' => true]);

        return back()->with('success', "LLM Provider '{$llmProvider->name}' is now set as default.");
    }

    public function testConnection(LlmProvider $llmProvider)
    {
        if (str_starts_with($llmProvider->api_key, 'sk-mock') || str_starts_with($llmProvider->api_key, 'mock-')) {
            LlmLog::record(
                provider: $llmProvider,
                endpoint: 'mock://test-connection',
                httpMethod: 'GET',
                requestPayload: null,
                statusCode: 200,
                responseBody: '{"status": "simulated_ok"}',
                latencyMs: 15,
                isSuccess: true,
                errorMessage: null,
                action: 'connection_test'
            );

            return back()->with('success', 'Mock provider connection simulated successfully.');
        }

        $startTime = microtime(true);
        try {
            if ($llmProvider->provider_type === 'gemini') {
                $baseUrl = rtrim($llmProvider->base_url ?: 'https://generativelanguage.googleapis.com', '/');
                if (str_contains($baseUrl, 'api.openai.com')) {
                    $baseUrl = 'https://generativelanguage.googleapis.com';
                }
                $endpoint = "{$baseUrl}/v1beta/models?key={$llmProvider->api_key}";

                $response = Http::timeout(8)->get($endpoint);
                $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

                LlmLog::record(
                    provider: $llmProvider,
                    endpoint: $endpoint,
                    httpMethod: 'GET',
                    requestPayload: null,
                    statusCode: $response->status(),
                    responseBody: $response->body(),
                    latencyMs: $latencyMs,
                    isSuccess: $response->successful(),
                    errorMessage: $response->successful() ? null : $response->body(),
                    action: 'connection_test'
                );

                if ($response->successful()) {
                    return back()->with('success', "Connection to Google Gemini ({$llmProvider->name}) successful! Models API responded 200 OK.");
                }

                $errorData = $response->json();
                $errorMsg = $errorData['error']['message'] ?? $response->body();

                return back()->with('error', "Google Gemini API error ({$response->status()}): {$errorMsg}");
            }

            if ($llmProvider->provider_type === 'anthropic') {
                $baseUrl = rtrim($llmProvider->base_url ?: 'https://api.anthropic.com', '/');
                if (str_contains($baseUrl, 'api.openai.com')) {
                    $baseUrl = 'https://api.anthropic.com';
                }
                $endpoint = "{$baseUrl}/v1/models";

                $response = Http::withHeaders([
                    'x-api-key' => $llmProvider->api_key,
                    'anthropic-version' => '2023-06-01',
                ])->timeout(8)->get($endpoint);
                $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

                LlmLog::record(
                    provider: $llmProvider,
                    endpoint: $endpoint,
                    httpMethod: 'GET',
                    requestPayload: null,
                    statusCode: $response->status(),
                    responseBody: $response->body(),
                    latencyMs: $latencyMs,
                    isSuccess: $response->successful(),
                    errorMessage: $response->successful() ? null : $response->body(),
                    action: 'connection_test'
                );

                if ($response->successful()) {
                    return back()->with('success', "Connection to Anthropic Claude ({$llmProvider->name}) successful!");
                }

                $errorData = $response->json();
                $errorMsg = $errorData['error']['message'] ?? $response->body();

                return back()->with('error', "Anthropic API error ({$response->status()}): {$errorMsg}");
            }

            // Default: OpenAI / OpenRouter / Custom compatible gateway
            $baseUrl = rtrim($llmProvider->base_url ?: 'https://api.openai.com/v1', '/');
            $endpoint = $baseUrl.'/models';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$llmProvider->api_key,
            ])->timeout(8)->get($endpoint);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            LlmLog::record(
                provider: $llmProvider,
                endpoint: $endpoint,
                httpMethod: 'GET',
                requestPayload: null,
                statusCode: $response->status(),
                responseBody: $response->body(),
                latencyMs: $latencyMs,
                isSuccess: $response->successful(),
                errorMessage: $response->successful() ? null : $response->body(),
                action: 'connection_test'
            );

            if ($response->successful()) {
                return back()->with('success', "Connection to {$llmProvider->name} successful!");
            }

            $errorData = $response->json();
            $errorMsg = $errorData['error']['message'] ?? $response->body();

            return back()->with('error', "API returned status {$response->status()}: {$errorMsg}");
        } catch (\Exception $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            LlmLog::record(
                provider: $llmProvider,
                endpoint: $llmProvider->base_url ?: 'https://api.openai.com/v1',
                httpMethod: 'GET',
                requestPayload: null,
                statusCode: 0,
                responseBody: null,
                latencyMs: $latencyMs,
                isSuccess: false,
                errorMessage: $e->getMessage(),
                action: 'connection_test'
            );

            return back()->with('error', 'Connection failed: '.$e->getMessage());
        }
    }

    public function logs(Request $request, LlmProvider $llmProvider)
    {
        $query = $llmProvider->logs();

        if ($request->filled('chat_session_id')) {
            $query->where('chat_session_id', $request->input('chat_session_id'));
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.llm_providers.logs', compact('llmProvider', 'logs'));
    }

    public function clearLogs(LlmProvider $llmProvider)
    {
        $llmProvider->logs()->delete();

        return back()->with('success', "Diagnostic logs for '{$llmProvider->name}' have been cleared.");
    }

    public function testPrompt(Request $request, LlmProvider $llmProvider, LlmAdapterFactory $adapterFactory)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
        ]);

        $prompt = $request->input('prompt');
        $adapter = $adapterFactory->make($llmProvider);

        $response = $adapter->send(
            $llmProvider,
            'You are a helpful AI diagnostic assistant. Reply concisely.',
            [['role' => 'user', 'content' => $prompt]],
            []
        );

        if ($response->isError()) {
            return back()->with('error', "Live diagnostic test failed: {$response->errorMessage}");
        }

        return back()->with('success', "Live diagnostic response: \"{$response->content}\" (Tokens: {$response->promptTokens} prompt / {$response->completionTokens} completion)");
    }

    public function destroy(LlmProvider $llmProvider)
    {
        $llmProvider->delete();

        return redirect()->route('admin.llm-providers.index')->with('success', 'LLM Provider deleted.');
    }
}
