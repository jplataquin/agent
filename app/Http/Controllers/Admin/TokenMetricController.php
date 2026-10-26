<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenMetric;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokenMetricController extends Controller
{
    public function index(Request $request)
    {
        $metricsQuery = TokenMetric::with(['user', 'chatSession'])->latest();

        if ($request->filled('user_id')) {
            $metricsQuery->where('user_id', $request->input('user_id'));
        }

        $metrics = $metricsQuery->paginate(20);

        // Aggregations
        $totalTokens = TokenMetric::sum('total_tokens');
        $totalPromptTokens = TokenMetric::sum('prompt_tokens');
        $totalCompletionTokens = TokenMetric::sum('completion_tokens');
        $totalEstimatedCost = TokenMetric::sum('estimated_cost');

        // Usage by Model
        $modelBreakdown = TokenMetric::select('model', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(estimated_cost) as cost'))
            ->groupBy('model')
            ->get();

        // Usage by User
        $userBreakdown = TokenMetric::select('user_id', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(estimated_cost) as cost'))
            ->groupBy('user_id')
            ->with('user')
            ->take(10)
            ->get();

        $allUsers = User::orderBy('name')->get();

        return view('admin.metrics.index', compact(
            'metrics',
            'totalTokens',
            'totalPromptTokens',
            'totalCompletionTokens',
            'totalEstimatedCost',
            'modelBreakdown',
            'userBreakdown',
            'allUsers'
        ));
    }
}
