@extends('layouts.app')

@section('content')
<div class="container py-2">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-chat-square-text text-primary me-2"></i>User Chat Sessions & Logs</h4>
            <p class="text-muted small mb-0">Audit and inspect all regular user conversations and AI assistant replies recorded per session.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <a href="{{ route('chat.index') }}" class="btn btn-primary btn-sm rounded-pill px-3">
                <i class="bi bi-chat-dots me-1"></i> Open AI Chat
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="text-muted small fw-semibold text-uppercase">Recorded Sessions</div>
                <h3 class="fw-bold my-1 text-primary">{{ number_format($kpi['total_sessions']) }}</h3>
                <small class="text-muted">{{ $kpi['active_chatters_count'] }} distinct user(s)</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="text-muted small fw-semibold text-uppercase">User Prompts</div>
                <h3 class="fw-bold my-1 text-info">{{ number_format($kpi['total_user_prompts']) }}</h3>
                <small class="text-muted">Recorded user queries</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="text-muted small fw-semibold text-uppercase">Assistant Replies</div>
                <h3 class="fw-bold my-1 text-success">{{ number_format($kpi['total_assistant_replies']) }}</h3>
                <small class="text-muted">AI responses delivered</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <div class="text-muted small fw-semibold text-uppercase">Est. Token Cost</div>
                <h3 class="fw-bold my-1 text-warning">${{ number_format($kpi['total_regular_cost'], 4) }}</h3>
                <small class="text-muted">{{ number_format($kpi['total_regular_tokens']) }} tokens consumed</small>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('admin.user-chats.index') }}" method="GET" class="row g-2 align-items-center">
                <!-- User Selector -->
                <div class="col-12 col-md-3">
                    <label class="small text-muted mb-1">Filter by User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All Regular Users</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }} ({{ $u->email }}) [{{ ucfirst($u->role) }}]
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Role Filter -->
                <div class="col-6 col-md-2">
                    <label class="small text-muted mb-1">Role Filter</label>
                    <select name="role" class="form-select form-select-sm">
                        <option value="regular" {{ request('role', 'regular') === 'regular' ? 'selected' : '' }}>Regular Users Only</option>
                        <option value="all" {{ request('role') === 'all' ? 'selected' : '' }}>All Users (Incl. Admin)</option>
                        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admins Only</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-6 col-md-2">
                    <label class="small text-muted mb-1">Session Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="" {{ !request('status') ? 'selected' : '' }}>All Sessions</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Deleted by User</option>
                    </select>
                </div>

                <!-- Search Input -->
                <div class="col-12 col-md-3">
                    <label class="small text-muted mb-1">Search Keywords</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Search title or content..." value="{{ request('search') }}">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="col-12 col-md-2 d-flex align-items-end gap-1 mt-auto">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-filter"></i> Apply
                    </button>
                    @if(request()->anyFilled(['user_id', 'search', 'status', 'date_from', 'date_to']) || (request('role') && request('role') !== 'regular'))
                        <a href="{{ route('admin.user-chats.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Sessions Table Card -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-journal-text me-2 text-primary"></i>Recorded Conversations
                <span class="badge bg-secondary-subtle text-secondary ms-1">{{ $sessions->total() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 50px;">ID</th>
                            <th>User</th>
                            <th>Conversation Title</th>
                            <th>Prompts</th>
                            <th>Replies</th>
                            <th>Status</th>
                            <th>Recorded On</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $session)
                            <tr>
                                <td class="ps-3 font-monospace small text-muted">#{{ $session->id }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                            {{ strtoupper(substr($session->user->name ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $session->user->name ?? 'Unknown User' }}</div>
                                            <small class="text-muted">{{ $session->user->email ?? 'N/A' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-truncate" style="max-width: 320px;">
                                        <a href="{{ route('admin.user-chats.show', $session->id) }}" class="text-decoration-none text-dark link-primary">
                                            {{ $session->title }}
                                        </a>
                                    </div>
                                    <small class="text-muted">
                                        {{ $session->messages_count }} total interaction(s)
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <i class="bi bi-person me-1"></i>{{ $session->user_messages_count }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="bi bi-robot me-1"></i>{{ $session->assistant_messages_count }}
                                    </span>
                                </td>
                                <td>
                                    @if($session->trashed())
                                        <span class="badge bg-secondary-subtle text-secondary" title="Deleted by user on {{ $session->deleted_at->format('Y-m-d H:i') }}">
                                            <i class="bi bi-archive me-1"></i>Deleted by user
                                        </span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="bi bi-check2-circle me-1"></i>Active
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="small">{{ $session->created_at->format('M d, Y H:i') }}</div>
                                    <small class="text-muted">{{ $session->updated_at->diffForHumans() }}</small>
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.user-chats.show', $session->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Inspect Full Transcript">
                                            <i class="bi bi-eye me-1"></i> Transcript
                                        </a>

                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 30px; height: 30px; padding: 0;">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <li>
                                                    <a class="dropdown-item small" href="{{ route('admin.user-chats.export', ['session' => $session->id, 'format' => 'txt']) }}">
                                                        <i class="bi bi-file-text me-2"></i> Export TXT
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item small" href="{{ route('admin.user-chats.export', ['session' => $session->id, 'format' => 'json']) }}">
                                                        <i class="bi bi-filetype-json me-2"></i> Export JSON
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('admin.user-chats.destroy', $session->id) }}" method="POST" onsubmit="return confirm('Permanently purge this conversation from audit logs? This cannot be undone.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item small text-danger">
                                                            <i class="bi bi-trash3 me-2"></i> Purge Record
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-chat-square-dots fs-1 d-block mb-2 text-secondary"></i>
                                    <h6>No recorded conversation sessions found</h6>
                                    <p class="small text-muted mb-0">User chat prompts and replies will automatically be recorded here as regular users interact with the AI assistant.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($sessions->hasPages())
                <div class="p-3 border-top">
                    {{ $sessions->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
