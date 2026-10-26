<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LlmProvider;
use App\Models\MasterPrompt;
use App\Models\McpServer;
use App\Models\Skill;
use App\Models\TokenMetric;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'regular_users' => User::where('role', User::ROLE_REGULAR)->count(),
            'admin_users' => User::where('role', User::ROLE_ADMIN)->count(),
            'total_skills' => Skill::count(),
            'active_skills' => Skill::where('is_active', true)->count(),
            'total_mcp_servers' => McpServer::count(),
            'active_mcp_servers' => McpServer::where('is_active', true)->count(),
            'total_master_prompts' => MasterPrompt::count(),
            'active_prompt' => MasterPrompt::where('is_active', true)->first(),
            'default_provider' => LlmProvider::getDefault(),
            'total_tokens' => TokenMetric::sum('total_tokens'),
            'total_cost' => TokenMetric::sum('estimated_cost'),
        ];

        $recentMetrics = TokenMetric::with('user')->latest()->take(6)->get();

        return view('admin.dashboard', compact('stats', 'recentMetrics'));
    }
}
