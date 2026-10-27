<?php

namespace App\Http\Controllers;

use App\Models\LlmLog;
use App\Models\LlmProvider;
use App\Services\GeminiDiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeminiDiagnosticController extends Controller
{
    public function __construct(
        protected GeminiDiagnosticService $diagnosticService
    ) {}

    /**
     * Display the public Gemini 3 Diagnostics interface.
     */
    public function index(Request $request): View
    {
        $providers = LlmProvider::where('provider_type', 'gemini')->get();
        $defaultProvider = $providers->where('is_default', true)->first() ?: $providers->first();

        $recentLogs = LlmLog::whereHas('provider', function ($q) {
            $q->where('provider_type', 'gemini');
        })
            ->orWhere('endpoint', 'like', '%generativelanguage.googleapis.com%')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $models = [
            'gemini-3.8-flash' => 'Gemini 3.8 Flash (Agentic & Long-Horizon Reasoning)',
            'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro Preview (Complex Engineering Reasoning)',
            'gemini-3.5-flash' => 'Gemini 3.5 Flash (High-Speed Frontier Intelligence)',
            'gemini-3-flash-preview' => 'Gemini 3 Flash Preview',
            'gemini-2.0-flash-thinking-exp' => 'Gemini 2.0 Flash Thinking Experimental',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash (Stable)',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro (Stable)',
        ];

        $diagnosticReport = session('diagnostic_report');

        return view('diagnostics.gemini', compact('providers', 'defaultProvider', 'recentLogs', 'models', 'diagnosticReport'));
    }

    /**
     * Execute live diagnostics from web UI or JSON request.
     */
    public function run(Request $request): mixed
    {
        $validated = $request->validate([
            'provider_id' => 'nullable|integer',
            'api_key' => 'nullable|string',
            'model' => 'nullable|string',
            'thinking_level' => 'nullable|string|in:minimal,low,medium,high',
        ]);

        $report = $this->diagnosticService->runDiagnostics($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($report);
        }

        return redirect()->route('diagnostics.gemini')
            ->with('diagnostic_report', $report);
    }

    /**
     * Dedicated programmatic API endpoint for remote automated diagnostics.
     */
    public function apiRun(Request $request): JsonResponse
    {
        $params = $request->only(['provider_id', 'api_key', 'model', 'thinking_level', 'base_url']);
        $report = $this->diagnosticService->runDiagnostics($params);

        $httpStatus = ($report['summary']['all_passed'] ?? false) ? 200 : 422;

        return response()->json($report, $httpStatus);
    }

    /**
     * Quick status check of configured Gemini providers and system health.
     */
    public function status(): JsonResponse
    {
        $geminiProviders = LlmProvider::where('provider_type', 'gemini')->get();
        $activeProvider = $geminiProviders->where('is_active', true)->where('is_default', true)->first()
            ?: $geminiProviders->where('is_active', true)->first();

        return response()->json([
            'status' => 'ok',
            'active_provider' => $activeProvider ? [
                'id' => $activeProvider->id,
                'name' => $activeProvider->name,
                'model' => $activeProvider->model,
                'has_key' => ! empty($activeProvider->api_key),
                'is_mock' => str_starts_with($activeProvider->api_key, 'sk-mock') || str_starts_with($activeProvider->api_key, 'mock-'),
            ] : null,
            'total_providers' => $geminiProviders->count(),
            'server_environment' => [
                'php' => PHP_VERSION,
                'curl' => function_exists('curl_version') ? (curl_version()['version'] ?? null) : null,
            ],
        ]);
    }
}
