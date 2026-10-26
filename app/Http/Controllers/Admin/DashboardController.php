<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
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
        $regularUserIds = User::where('role', User::ROLE_REGULAR)->pluck('id');

        $stats = [
            'total_users' => User::count(),
            'regular_users' => $regularUserIds->count(),
            'admin_users' => User::where('role', User::ROLE_ADMIN)->count(),
            'regular_sessions' => ChatSession::withTrashed()->whereIn('user_id', $regularUserIds)->count(),
            'regular_messages' => ChatMessage::whereHas('session', function ($q) use ($regularUserIds) {
                $q->withTrashed()->whereIn('user_id', $regularUserIds);
            })->count(),
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

        $recentSessions = ChatSession::withTrashed()
            ->whereIn('user_id', $regularUserIds)
            ->with(['user'])
            ->withCount(['messages', 'userMessages', 'assistantMessages'])
            ->latest('updated_at')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentMetrics', 'recentSessions'));
    }
}
