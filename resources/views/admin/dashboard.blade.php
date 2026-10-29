@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-speedometer2 text-primary me-2"></i>Admin Console</h3>
            <p class="text-muted small mb-0">Manage users, AI skills, HTTP MCP servers, system prompts, and monitor LLM token usage.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.mcp-diagnostics.index') }}" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="bi bi-heart-pulse me-1"></i> MCP Diagnostics
            </a>
            <a href="{{ route('admin.internal-tools.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-terminal me-1"></i> Test Internal Tools
            </a>
            <a href="{{ route('chat.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="bi bi-chat-dots me-1"></i> Open Chat
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Users</div>
                        <h4 class="fw-bold my-1">{{ $stats['total_users'] }}</h4>
                        <span class="badge bg-secondary-subtle text-secondary small">{{ $stats['regular_users'] }} Regular</span>
                    </div>
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 fs-4"><i class="bi bi-people"></i></div>
                </div>
                <a href="{{ route('admin.users.index') }}" class="small text-decoration-none mt-3 d-inline-block">Manage Users &rarr;</a>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">User Chats</div>
                        <h4 class="fw-bold my-1 text-info">{{ number_format($stats['regular_sessions']) }}</h4>
                        <span class="badge bg-info-subtle text-info small">{{ number_format($stats['regular_messages']) }} Messages Logged</span>
                    </div>
                    <div class="p-2 bg-info-subtle text-info rounded-3 fs-4"><i class="bi bi-chat-square-text"></i></div>
                </div>
                <a href="{{ route('admin.user-chats.index') }}" class="small text-decoration-none mt-3 d-inline-block text-info fw-semibold">Audit Chat Logs &rarr;</a>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">AI Skills</div>
                        <h4 class="fw-bold my-1">{{ $stats['total_skills'] }}</h4>
                        <span class="badge bg-success-subtle text-success small">{{ $stats['active_skills'] }} Active</span>
                    </div>
                    <div class="p-2 bg-success-subtle text-success rounded-3 fs-4"><i class="bi bi-tools"></i></div>
                </div>
                <a href="{{ route('admin.skills.index') }}" class="small text-decoration-none mt-3 d-inline-block">Configure Skills &rarr;</a>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">MCP Servers</div>
                        <h4 class="fw-bold my-1">{{ $stats['total_mcp_servers'] }}</h4>
                        <span class="badge bg-secondary-subtle text-secondary small">{{ $stats['active_mcp_servers'] }} Online</span>
                    </div>
                    <div class="p-2 bg-secondary-subtle text-secondary rounded-3 fs-4"><i class="bi bi-hdd-network"></i></div>
                </div>
                <a href="{{ route('admin.mcp-servers.index') }}" class="small text-decoration-none mt-3 d-inline-block">MCP Connections &rarr;</a>
            </div>
        </div>

        <div class="col-12 col-lg-8 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Tokens</div>
                        <h4 class="fw-bold my-1">{{ number_format($stats['total_tokens']) }}</h4>
                        <span class="badge bg-warning-subtle text-warning small">${{ number_format($stats['total_cost'], 4) }} Est.</span>
                    </div>
                    <div class="p-2 bg-warning-subtle text-warning rounded-3 fs-4"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <a href="{{ route('admin.metrics.index') }}" class="small text-decoration-none mt-3 d-inline-block">Usage Details &rarr;</a>
            </div>
        </div>
    </div>

    <!-- System Status & Quick Links -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-cpu me-2 text-primary"></i>Active LLM Provider</h6>
                </div>
                <div class="card-body">
                    @if($stats['default_provider'])
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <h5 class="fw-bold mb-0">{{ $stats['default_provider']->name }}</h5>
                                <div class="text-muted small">Model: <code>{{ $stats['default_provider']->model }}</code></div>
                            </div>
                            <span class="badge bg-success">Default Active</span>
                        </div>
                        <p class="text-muted small">Base URL: {{ $stats['default_provider']->base_url ?: 'https://api.openai.com/v1' }}</p>
                    @else
                        <div class="alert alert-warning py-2 small mb-2">
                            No default LLM provider active! The AI agent requires an active provider.
                        </div>
                    @endif
                    <a href="{{ route('admin.llm-providers.index') }}" class="btn btn-sm btn-outline-primary rounded-pill">Manage Providers & Keys</a>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h6 class="fw-bold mb-0"><i class="bi bi-file-text me-2 text-primary"></i>Active Master Prompt</h6>
                </div>
                <div class="card-body">
                    @if($stats['active_prompt'])
                        <h6 class="fw-bold mb-1">{{ $stats['active_prompt']->title }}</h6>
                        <p class="text-muted small text-truncate-3 mb-3">
                            {{ Str::limit($stats['active_prompt']->content, 140) }}
                        </p>
                    @else
                        <div class="alert alert-warning py-2 small mb-2">
                            No active master prompt found.
                        </div>
                    @endif
                    <a href="{{ route('admin.master-prompts.index') }}" class="btn btn-sm btn-outline-primary rounded-pill">Manage System Prompts</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent User Conversations & Chat Audits -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="bi bi-chat-quote me-2 text-info"></i>Recent Regular User Chat Sessions</h6>
            <a href="{{ route('admin.user-chats.index') }}" class="small text-decoration-none">View All Chat Logs &rarr;</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">User</th>
                            <th>Conversation Title</th>
                            <th>Prompts</th>
                            <th>Replies</th>
                            <th>Status</th>
                            <th>Last Active</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSessions as $session)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $session->user->name }}</div>
                                    <small class="text-muted">{{ $session->user->email }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-truncate" style="max-width: 280px;">{{ $session->title }}</div>
                                    <small class="text-muted font-monospace">Session #{{ $session->id }}</small>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary">{{ $session->user_messages_count }}</span></td>
                                <td><span class="badge bg-success-subtle text-success">{{ $session->assistant_messages_count }}</span></td>
                                <td>
                                    @if($session->trashed())
                                        <span class="badge bg-secondary-subtle text-secondary">Deleted by user</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $session->updated_at->diffForHumans() }}</td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('admin.user-chats.show', $session->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="bi bi-eye me-1"></i> View Transcript
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted small">No regular user chat sessions recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Token Metrics -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-secondary"></i>Recent AI Agent Invocations</h6>
            <a href="{{ route('admin.metrics.index') }}" class="small text-decoration-none">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">User</th>
                            <th>Model</th>
                            <th>Tokens</th>
                            <th>Est. Cost</th>
                            <th class="pe-3">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentMetrics as $metric)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $metric->user?->name ?? 'Unknown' }}</td>
                                <td><span class="badge bg-secondary-subtle text-dark">{{ $metric->model }}</span></td>
                                <td>{{ number_format($metric->total_tokens) }}</td>
                                <td>${{ number_format($metric->estimated_cost, 4) }}</td>
                                <td class="pe-3 text-muted small">{{ $metric->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">No metrics recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
