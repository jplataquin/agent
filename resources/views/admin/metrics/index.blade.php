@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Token Consumption Metrics</h4>
            <p class="text-muted small mb-0">Monitor LLM token usage, prompt vs completion balance, and cost metrics across the company.</p>
        </div>
        <div>
            <form action="{{ route('admin.metrics.index') }}" method="GET" class="d-flex gap-2">
                <select name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Users</option>
                    @foreach($allUsers as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
                @if(request('user_id'))
                    <a href="{{ route('admin.metrics.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                @endif
            </form>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="text-muted small fw-semibold text-uppercase">Total Tokens</div>
                <h3 class="fw-bold my-1 text-primary">{{ number_format($totalTokens) }}</h3>
                <small class="text-muted">Across all conversations</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="text-muted small fw-semibold text-uppercase">Prompt Tokens</div>
                <h3 class="fw-bold my-1">{{ number_format($totalPromptTokens) }}</h3>
                <small class="text-muted">Input context & MCP results</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="text-muted small fw-semibold text-uppercase">Completion Tokens</div>
                <h3 class="fw-bold my-1">{{ number_format($totalCompletionTokens) }}</h3>
                <small class="text-muted">Generated AI responses</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="text-muted small fw-semibold text-uppercase">Estimated Cost</div>
                <h3 class="fw-bold my-1 text-success">${{ number_format($totalEstimatedCost, 4) }}</h3>
                <small class="text-muted">Based on model tier</small>
            </div>
        </div>
    </div>

    <!-- Model and User Breakdowns -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-cpu me-2"></i>Usage by Model</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3">Model</th>
                                <th>Total Tokens</th>
                                <th class="pe-3 text-end">Est. Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($modelBreakdown as $mb)
                                <tr>
                                    <td class="ps-3 font-monospace fw-semibold">{{ $mb->model }}</td>
                                    <td>{{ number_format($mb->tokens) }}</td>
                                    <td class="pe-3 text-end">${{ number_format($mb->cost, 4) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted small">No data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-lines-fill me-2"></i>Top Users by Consumption</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="ps-3">User</th>
                                <th>Total Tokens</th>
                                <th class="pe-3 text-end">Est. Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($userBreakdown as $ub)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $ub->user?->name ?? 'User #'.$ub->user_id }}</td>
                                    <td>{{ number_format($ub->tokens) }}</td>
                                    <td class="pe-3 text-end">${{ number_format($ub->cost, 4) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted small">No data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Invocations Log -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="fw-bold mb-0"><i class="bi bi-list-task me-2"></i>Invocation Audit Log</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-3">Timestamp</th>
                        <th>User</th>
                        <th>Model</th>
                        <th>Prompt</th>
                        <th>Completion</th>
                        <th>Total</th>
                        <th class="pe-3 text-end">Est. Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($metrics as $m)
                        <tr>
                            <td class="ps-3 small text-muted">{{ $m->created_at->format('M d, H:i') }}</td>
                            <td class="fw-semibold">{{ $m->user?->name ?? 'Unknown' }}</td>
                            <td><span class="badge bg-secondary-subtle text-dark">{{ $m->model }}</span></td>
                            <td>{{ number_format($m->prompt_tokens) }}</td>
                            <td>{{ number_format($m->completion_tokens) }}</td>
                            <td class="fw-bold">{{ number_format($m->total_tokens) }}</td>
                            <td class="pe-3 text-end text-success">${{ number_format($m->estimated_cost, 4) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No invocation metrics recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($metrics->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $metrics->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
