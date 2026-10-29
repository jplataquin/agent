@extends('layouts.app')

@push('styles')
<style>
    .transcript-container {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .transcript-bubble {
        border-radius: 14px;
        padding: 1.1rem 1.25rem;
        max-width: 88%;
        position: relative;
        word-break: break-word;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .bubble-user {
        background-color: #0d6efd;
        color: #ffffff;
        border-bottom-right-radius: 4px;
        margin-left: auto;
    }
    .bubble-assistant {
        background-color: #ffffff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
        margin-right: auto;
    }
    .bubble-tool {
        background-color: #f8fafc;
        border-left: 4px solid #6366f1;
        font-family: monospace;
        font-size: 0.85rem;
        color: #334155;
        max-width: 95%;
        margin-right: auto;
    }
    .bubble-system {
        background-color: #fffbeb;
        border: 1px solid #fef3c7;
        color: #92400e;
        max-width: 95%;
        margin: 0 auto;
        font-size: 0.85rem;
    }

    [data-bs-theme="dark"] .bubble-assistant {
        background-color: #1e293b;
        color: #f1f5f9;
        border-color: #334155;
    }
    [data-bs-theme="dark"] .bubble-tool {
        background-color: #0f172a;
        color: #cbd5e1;
        border-color: #6366f1;
    }
    [data-bs-theme="dark"] .bubble-system {
        background-color: #2e1d0c;
        border-color: #78350f;
        color: #fde68a;
    }
</style>
@endpush

@section('content')
<div class="container py-2">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.user-chats.index') }}">User Chats</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Session #{{ $session->id }}</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <span>{{ $session->title }}</span>
                @if($session->trashed())
                    <span class="badge bg-secondary-subtle text-secondary fs-6">Deleted by user</span>
                @else
                    <span class="badge bg-success-subtle text-success fs-6">Active Session</span>
                @endif
            </h4>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.user-chats.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Back to Chats
            </a>
            <div class="btn-group">
                <a href="{{ route('admin.user-chats.export', ['session' => $session->id, 'format' => 'txt']) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-file-text me-1"></i> TXT
                </a>
                <a href="{{ route('admin.user-chats.export', ['session' => $session->id, 'format' => 'json']) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-filetype-json me-1"></i> JSON
                </a>
            </div>
            <form action="{{ route('admin.user-chats.destroy', $session->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to permanently purge this session and all its messages?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    <i class="bi bi-trash3 me-1"></i> Purge
                </button>
            </form>
        </div>
    </div>

    <!-- Metadata Card -->
    <div class="row g-3 mb-4">
        <!-- User Details -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <div class="text-muted small fw-semibold text-uppercase mb-2">Participant Details</div>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 48px; height: 48px;">
                        {{ strtoupper(substr($session->user->name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">{{ $session->user->name ?? 'Unknown User' }}</h6>
                        <div class="small text-muted">{{ $session->user->email ?? 'N/A' }}</div>
                        <span class="badge {{ $session->user->isAdmin() ? 'bg-danger' : 'bg-primary' }} rounded-pill small mt-1">
                            {{ ucfirst($session->user->role) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Statistics -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <div class="text-muted small fw-semibold text-uppercase mb-2">Session Activity</div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted">User Prompts:</span>
                    <span class="small fw-bold">{{ $session->user_messages_count }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted">AI Replies:</span>
                    <span class="small fw-bold">{{ $session->assistant_messages_count }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted">Created:</span>
                    <span class="small">{{ $session->created_at->format('M d, Y H:i:s') }}</span>
                </div>
                @if($session->trashed())
                    <div class="d-flex justify-content-between">
                        <span class="small text-danger">User Deleted At:</span>
                        <span class="small text-danger">{{ $session->deleted_at->format('M d, Y H:i:s') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Token Usage -->
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <div class="text-muted small fw-semibold text-uppercase mb-2">LLM Consumption</div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted">Total Tokens:</span>
                    <span class="small fw-bold font-monospace">{{ number_format($tokenStats['total_tokens']) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="small text-muted">Prompt / Completion:</span>
                    <span class="small font-monospace">{{ number_format($tokenStats['prompt_tokens']) }} / {{ number_format($tokenStats['completion_tokens']) }}</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="small text-muted">Est. Cost:</span>
                    <span class="small fw-bold text-success font-monospace">${{ number_format($tokenStats['estimated_cost'], 4) }}</span>
                </div>
                @if($tokenStats['models']->isNotEmpty())
                    <div class="mt-2 pt-2 border-top small text-muted">
                        Models: <code>{{ $tokenStats['models']->implode(', ') }}</code>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Transcript Container -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-chat-left-text me-2 text-primary"></i>Complete Conversation Transcript
            </h6>
            <span class="badge bg-secondary-subtle text-secondary">
                {{ $session->messages->count() }} Message(s) Logged
            </span>
        </div>
        <div class="card-body p-4 bg-body-secondary">
            <div class="transcript-container">
                @forelse($session->messages as $msg)
                    @if($msg->role === 'user')
                        <!-- User Message -->
                        <div class="transcript-bubble bubble-user">
                            <div class="d-flex justify-content-between align-items-center mb-1 small opacity-75">
                                <span class="fw-bold"><i class="bi bi-person me-1"></i>{{ $session->user->name }}</span>
                                <span>{{ $msg->created_at?->format('H:i:s M d') }}</span>
                            </div>
                            <div class="message-text" style="white-space: pre-wrap;">{{ $msg->content }}</div>
                        </div>

                    @elseif($msg->role === 'assistant')
                        <!-- Assistant Message -->
                        <div class="transcript-bubble bubble-assistant">
                            <div class="d-flex justify-content-between align-items-center mb-2 small text-muted border-bottom pb-1">
                                <span class="fw-bold text-primary"><i class="bi bi-robot me-1"></i>AI Assistant</span>
                                <div class="d-flex align-items-center gap-2">
                                    @if($msg->feedback === 'like')
                                        <span class="badge bg-success-subtle text-success"><i class="bi bi-hand-thumbs-up-fill me-1"></i>Liked</span>
                                    @elseif($msg->feedback === 'dislike')
                                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-hand-thumbs-down-fill me-1"></i>Disliked</span>
                                    @endif
                                    <span>{{ $msg->created_at?->format('H:i:s M d') }}</span>
                                </div>
                            </div>

                            @if(!empty($msg->tool_calls))
                                <div class="alert alert-info py-2 px-3 small mb-2">
                                    <i class="bi bi-tools me-1"></i> Requested Tool Invocation(s) (Internal / MCP):
                                    <ul class="mb-0 ps-3">
                                        @foreach($msg->tool_calls as $tc)
                                            <li>
                                                <code>{{ $tc['name'] ?? 'tool' }}</code>
                                                @if(!empty($tc['arguments']))
                                                    <pre class="bg-dark text-light p-2 rounded small mt-1 mb-1">{{ json_encode($tc['arguments'], JSON_PRETTY_PRINT) }}</pre>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="markdown-body" data-raw-content="{{ $msg->content }}">
                                {{ $msg->content }}
                            </div>
                        </div>

                    @elseif($msg->role === 'tool')
                        <!-- Tool Call Execution Result -->
                        <div class="transcript-bubble bubble-tool">
                            <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                                <span class="fw-bold"><i class="bi bi-terminal me-1"></i>Tool Execution Result (Call ID: {{ $msg->tool_call_id }})</span>
                                <span>{{ $msg->created_at?->format('H:i:s') }}</span>
                            </div>
                            <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.8rem;">{{ $msg->content }}</pre>
                        </div>

                    @else
                        <!-- System Message -->
                        <div class="transcript-bubble bubble-system">
                            <div class="d-flex justify-content-between align-items-center mb-1 small fw-bold">
                                <span><i class="bi bi-info-circle me-1"></i>System Directive</span>
                                <span>{{ $msg->created_at?->format('H:i:s') }}</span>
                            </div>
                            <div>{{ $msg->content }}</div>
                        </div>
                    @endif
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-chat-dots fs-1 d-block mb-2 text-secondary"></i>
                        <p class="mb-0">No messages recorded in this conversation.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof marked !== 'undefined') {
            document.querySelectorAll('.markdown-body').forEach(function(el) {
                const raw = el.getAttribute('data-raw-content');
                if (raw) {
                    el.innerHTML = marked.parse(raw);
                    if (typeof window.renderMath === 'function') {
                        window.renderMath(el);
                    }
                }
            });
        }
        if (typeof hljs !== 'undefined') {
            hljs.highlightAll();
        }
    });
</script>
@endpush
@endsection
