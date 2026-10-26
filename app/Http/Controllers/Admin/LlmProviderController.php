<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LlmProvider;
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
            return back()->with('success', 'Mock provider connection simulated successfully.');
        }

        try {
            if ($llmProvider->provider_type === 'gemini') {
                $baseUrl = rtrim($llmProvider->base_url ?: 'https://generativelanguage.googleapis.com', '/');
                if (str_contains($baseUrl, 'api.openai.com')) {
                    $baseUrl = 'https://generativelanguage.googleapis.com';
                }
                $endpoint = "{$baseUrl}/v1beta/models?key={$llmProvider->api_key}";

                $response = Http::timeout(8)->get($endpoint);

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

            if ($response->successful()) {
                return back()->with('success', "Connection to {$llmProvider->name} successful!");
            }

            $errorData = $response->json();
            $errorMsg = $errorData['error']['message'] ?? $response->body();

            return back()->with('error', "API returned status {$response->status()}: {$errorMsg}");
        } catch (\Exception $e) {
            return back()->with('error', 'Connection failed: '.$e->getMessage());
        }
    }

    public function destroy(LlmProvider $llmProvider)
    {
        $llmProvider->delete();

        return redirect()->route('admin.llm-providers.index')->with('success', 'LLM Provider deleted.');
    }
}
