@extends('layouts.app')

@push('styles')
<style>
    .json-tree-container {
        max-height: 480px;
        overflow-y: auto;
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.85rem;
        background-color: var(--bs-tertiary-bg, #f8f9fa);
        color: var(--bs-body-color, #212529);
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: 0.375rem;
        padding: 0.75rem;
    }
    .json-tree-details {
        margin: 0.2rem 0;
    }
    .json-summary {
        cursor: pointer;
        outline: none;
        user-select: none;
        list-style: none;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .json-summary::-webkit-details-marker {
        display: none;
    }
    .json-summary::before {
        content: "▶";
        font-size: 0.65rem;
        display: inline-block;
        transition: transform 0.15s ease;
        color: var(--bs-secondary, #6c757d);
    }
    .json-tree-details[open] > .json-summary::before {
        transform: rotate(90deg);
    }
    .json-tree-children {
        border-left: 1px dashed var(--bs-border-color, #dee2e6);
        margin-left: 0.4rem;
        padding-left: 0.75rem;
    }
    .json-key {
        color: #d63384;
        font-weight: 600;
    }
    .json-string {
        color: #198754;
        word-break: break-all;
    }
    .json-number {
        color: #fd7e14;
        font-weight: 500;
    }
    .json-boolean {
        color: #0dcaf0;
        font-weight: bold;
    }
    .json-null {
        color: #6c757d;
        font-style: italic;
    }
    .json-meta {
        color: var(--bs-secondary, #6c757d);
        font-size: 0.75rem;
    }
    .json-item {
        padding: 0.1rem 0;
    }

    [data-bs-theme="dark"] .json-tree-container {
        background-color: #1e293b;
        color: #f1f5f9;
        border-color: #334155;
    }
    [data-bs-theme="dark"] .json-key {
        color: #f472b6;
    }
    [data-bs-theme="dark"] .json-string {
        color: #4ade80;
    }
    [data-bs-theme="dark"] .json-number {
        color: #fb923c;
    }
    [data-bs-theme="dark"] .json-boolean {
        color: #38bdf8;
    }
    [data-bs-theme="dark"] .json-meta {
        color: #94a3b8;
    }
    [data-bs-theme="dark"] .json-tree-children {
        border-color: #334155;
    }
</style>
@endpush

@section('content')
<div class="container py-3">
    <!-- Header with Provider Info and Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold mb-0">
                    <i class="bi bi-journal-code text-primary me-1"></i> Diagnostic Logs: {{ $llmProvider->name }}
                </h4>
                <span class="badge bg-dark font-monospace">{{ strtoupper($llmProvider->provider_type) }}</span>
                <span class="badge bg-secondary font-monospace">{{ $llmProvider->model }}</span>
                @if($llmProvider->is_default)
                    <span class="badge bg-success">Default Provider</span>
                @endif
            </div>
            <p class="text-muted small mb-0 mt-1">
                Inspect every live HTTP request payload, raw response, status code, latency, and error message sent to this LLM.
            </p>
        </div>
        <div class="d-flex gap-2">
            @if($logs->total() > 0)
                <form action="{{ route('admin.llm-providers.logs.clear', $llmProvider) }}" method="POST" onsubmit="return confirm('Clear all diagnostic logs for this provider?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill">
                        <i class="bi bi-trash me-1"></i> Clear Logs
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.llm-providers.edit', $llmProvider) }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-pencil me-1"></i> Edit Provider
            </a>
            <a href="{{ route('admin.llm-providers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                Back to Providers
            </a>
        </div>
    </div>

    <!-- Live Test Prompt Box -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h6 class="fw-bold mb-0 text-primary">
                <i class="bi bi-play-circle me-1"></i> Test Live Prompt Against {{ $llmProvider->name }}
            </h6>
            <p class="text-muted small mb-0">Send a quick diagnostic prompt to verify this model's response and immediately generate a log entry.</p>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.llm-providers.test-prompt', $llmProvider) }}" method="POST">
                @csrf
                <div class="input-group">
                    <input type="text" name="prompt" class="form-control" placeholder="e.g. Respond with: 'Gemini is fully operational' and tell me today's date." value="{{ old('prompt', 'Hello! Please verify that your API connection is fully working.') }}" required>
                    <button type="submit" class="btn btn-primary fw-semibold px-4">
                        <i class="bi bi-send me-1"></i> Execute Live Test
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body py-2">
            <form action="{{ route('admin.llm-providers.logs', $llmProvider) }}" method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label small fw-bold text-muted text-uppercase">
                        <i class="bi bi-funnel me-1"></i> Filter:
                    </label>
                </div>
                <div class="col-auto">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-chat-left-dots"></i></span>
                        <input type="number" name="chat_session_id" class="form-control" placeholder="Chat Session ID..." value="{{ request('chat_session_id') }}">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-filter"></i> Apply Filter
                    </button>
                    @if(request()->filled('chat_session_id'))
                        <a href="{{ route('admin.llm-providers.logs', $llmProvider) }}" class="btn btn-sm btn-outline-secondary">
                            Reset
                        </a>
                    @endif
                </div>
                @if(request()->filled('chat_session_id'))
                    <div class="col-auto ms-auto">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">
                            Filtering by Session #{{ request('chat_session_id') }}
                        </span>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-transparent border-0 py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-list-check me-1"></i> Request &amp; Response History ({{ $logs->total() }} entries)
            </h6>
            <small class="text-muted">Showing latest {{ $logs->count() }} records</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-3" style="width: 170px;">Timestamp</th>
                        <th>Action</th>
                        <th>Session</th>
                        <th>Status</th>
                        <th>Latency</th>
                        <th>Tokens</th>
                        <th>Endpoint &amp; Result Summary</th>
                        <th class="text-end pe-3" style="width: 140px;">Inspection</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="ps-3 text-nowrap">
                                <div class="fw-semibold small">{{ $log->created_at->format('M d, Y H:i:s') }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">{{ $log->created_at->diffForHumans() }}</div>
                            </td>
                            <td>
                                @if($log->action === 'chat_completion')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Chat Message</span>
                                @elseif($log->action === 'test_prompt')
                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle">Live Test</span>
                                @elseif($log->action === 'connection_test')
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Connection Test</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">{{ $log->action }}</span>
                                @endif
                            </td>
                            <td>
                                @if($log->chat_session_id)
                                    <a href="{{ route('admin.user-chats.show', $log->chat_session_id) }}" class="badge bg-secondary-subtle text-decoration-none text-secondary-emphasis border border-secondary-subtle font-monospace" title="View Chat Transcript">
                                        #{{ $log->chat_session_id }}
                                    </a>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                @if($log->is_success)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle me-1"></i> {{ $log->status_code ?: 200 }} OK
                                    </span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="bi bi-x-circle me-1"></i> {{ $log->status_code ? $log->status_code.' Error' : 'Failed' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-nowrap small font-monospace">
                                {{ $log->latency_ms }} ms
                            </td>
                            <td class="text-nowrap small">
                                @if($log->prompt_tokens || $log->completion_tokens)
                                    <span class="text-muted">{{ $log->prompt_tokens }} in</span> / <span class="fw-semibold">{{ $log->completion_tokens }} out</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <div class="text-truncate font-monospace small" style="max-width: 300px;" title="{{ $log->endpoint }}">
                                    {{ $log->http_method }} {{ $log->endpoint }}
                                </div>
                                @if($log->error_message)
                                    <div class="text-danger small text-truncate mt-1" style="max-width: 340px;" title="{{ $log->error_message }}">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $log->error_message }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#logModal{{ $log->id }}">
                                    <i class="bi bi-code-slash me-1"></i> Inspect
                                </button>
                            </td>
                        </tr>

                        <!-- Detailed Inspection Modal -->
                        <div class="modal fade" id="logModal{{ $log->id }}" tabindex="-1" aria-labelledby="logModalLabel{{ $log->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <div>
                                            <h5 class="modal-title fw-bold" id="logModalLabel{{ $log->id }}">
                                                HTTP Inspection #{{ $log->id }} - {{ $llmProvider->name }}
                                            </h5>
                                            <span class="badge {{ $log->is_success ? 'bg-success' : 'bg-danger' }}">
                                                Status: {{ $log->status_code ?: 'Error' }}
                                            </span>
                                            <span class="badge bg-secondary ms-1">Latency: {{ $log->latency_ms }}ms</span>
                                            @if($log->chat_session_id)
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1 font-monospace">
                                                    Session #{{ $log->chat_session_id }}
                                                </span>
                                            @endif
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle ms-1">{{ $log->created_at->toDateTimeString() }}</span>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <!-- Endpoint & Details -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold text-muted small text-uppercase">Endpoint URL</label>
                                            <div class="p-2 bg-body-secondary rounded font-monospace small text-break">
                                                <span class="badge bg-dark me-1">{{ $log->http_method }}</span> {{ $log->endpoint }}
                                            </div>
                                        </div>

                                        @if($log->error_message)
                                            <div class="alert alert-danger py-2 small mb-3">
                                                <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i> Error Description</h6>
                                                <div class="text-break font-monospace">{{ $log->error_message }}</div>
                                            </div>
                                        @endif

                                        <div class="row">
                                            <!-- Request Payload Column -->
                                            <div class="col-lg-6 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="form-label fw-bold text-muted small text-uppercase mb-0">Request Payload</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-outline-secondary active view-toggle-btn" data-mode="tree" data-target-tree="req-tree-{{ $log->id }}" data-target-raw="req-raw-{{ $log->id }}">Tree</button>
                                                            <button type="button" class="btn btn-outline-secondary view-toggle-btn" data-mode="raw" data-target-tree="req-tree-{{ $log->id }}" data-target-raw="req-raw-{{ $log->id }}">Raw</button>
                                                        </div>
                                                        @if($log->request_payload)
                                                            <button class="btn btn-sm btn-link text-decoration-none py-0 copy-btn" data-target="req-raw-{{ $log->id }}">
                                                                <i class="bi bi-clipboard"></i> Copy
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Collapsible JSON Tree Container -->
                                                <div id="req-tree-{{ $log->id }}" class="json-tree-container" data-json="{{ is_array($log->request_payload) ? json_encode($log->request_payload) : ($log->request_payload ?: '') }}">
                                                    <span class="text-muted">Loading tree view...</span>
                                                </div>

                                                <!-- Raw JSON Pre Container -->
                                                <pre id="req-raw-{{ $log->id }}" class="bg-dark text-light p-3 rounded small font-monospace d-none" style="max-height: 480px; overflow-y: auto;">{{ $log->request_payload ? json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'No payload sent (GET request)' }}</pre>
                                            </div>

                                            <!-- Response Body Column -->
                                            <div class="col-lg-6 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="form-label fw-bold text-muted small text-uppercase mb-0">Raw Response Body</label>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-outline-secondary active view-toggle-btn" data-mode="tree" data-target-tree="res-tree-{{ $log->id }}" data-target-raw="res-raw-{{ $log->id }}">Tree</button>
                                                            <button type="button" class="btn btn-outline-secondary view-toggle-btn" data-mode="raw" data-target-tree="res-tree-{{ $log->id }}" data-target-raw="res-raw-{{ $log->id }}">Raw</button>
                                                        </div>
                                                        @if($log->response_body)
                                                            <button class="btn btn-sm btn-link text-decoration-none py-0 copy-btn" data-target="res-raw-{{ $log->id }}">
                                                                <i class="bi bi-clipboard"></i> Copy
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>

                                                @php
                                                    $decodedResp = json_decode($log->response_body, true);
                                                    $encodedResp = $decodedResp ? json_encode($decodedResp) : '';
                                                @endphp

                                                <!-- Collapsible JSON Tree Container -->
                                                <div id="res-tree-{{ $log->id }}" class="json-tree-container" data-json="{{ $encodedResp ?: ($log->response_body ?: '') }}">
                                                    <span class="text-muted">Loading tree view...</span>
                                                </div>

                                                <!-- Raw JSON Pre Container -->
                                                <pre id="res-raw-{{ $log->id }}" class="bg-dark text-light p-3 rounded small font-monospace d-none" style="max-height: 480px; overflow-y: auto;">@php
                                                    echo $decodedResp ? json_encode($decodedResp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ($log->response_body ?: 'No response body received.');
                                                @endphp</pre>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-text fs-2 text-muted d-block mb-2"></i>
                                <h6 class="fw-bold">No diagnostic logs recorded for this provider matching your criteria.</h6>
                                <p class="small text-muted mb-0">Use the Live Test Prompt above, send a message in AI Chat, or reset your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer bg-transparent border-0 py-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Copy button functionality
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const targetEl = document.getElementById(targetId);
            if (!targetEl) return;
            navigator.clipboard.writeText(targetEl.innerText).then(() => {
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check2 text-success"></i> Copied!';
                setTimeout(() => {
                    this.innerHTML = originalHtml;
                }, 2000);
            });
        });
    });

    // Toggle between Collapsible Tree View and Raw Pre View
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const mode = this.getAttribute('data-mode');
            const treeId = this.getAttribute('data-target-tree');
            const rawId = this.getAttribute('data-target-raw');
            const treeEl = document.getElementById(treeId);
            const rawEl = document.getElementById(rawId);

            const parentGroup = this.closest('.btn-group');
            if (parentGroup) {
                parentGroup.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
            }
            this.classList.add('active');

            if (mode === 'tree') {
                if (treeEl) treeEl.classList.remove('d-none');
                if (rawEl) rawEl.classList.add('d-none');
            } else {
                if (treeEl) treeEl.classList.add('d-none');
                if (rawEl) rawEl.classList.remove('d-none');
            }
        });
    });

    // Helper to escape HTML characters
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Build recursive collapsible JSON Tree HTML
    function buildJsonTree(val, key = null, isRoot = false) {
        if (val === null) {
            return `<div class="json-item">${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span class="json-null">null</span></div>`;
        }
        if (typeof val === 'boolean') {
            return `<div class="json-item">${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span class="json-boolean">${val}</span></div>`;
        }
        if (typeof val === 'number') {
            return `<div class="json-item">${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span class="json-number">${val}</span></div>`;
        }
        if (typeof val === 'string') {
            return `<div class="json-item">${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span class="json-string">"${escapeHtml(val)}"</span></div>`;
        }
        if (Array.isArray(val)) {
            if (val.length === 0) {
                return `<div class="json-item">${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span class="text-muted">[] (empty array)</span></div>`;
            }
            let itemsHtml = val.map((item, idx) => buildJsonTree(item, idx, false)).join('');
            return `
                <details class="json-tree-details" ${isRoot ? 'open' : ''}>
                    <summary class="json-summary">
                        ${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span class="json-meta">Array [${val.length}]</span>
                    </summary>
                    <div class="json-tree-children">
                        ${itemsHtml}
                    </div>
                </details>
            `;
        }
        if (typeof val === 'object') {
            const keys = Object.keys(val);
            if (keys.length === 0) {
                return `<div class="json-item">${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span class="text-muted">{} (empty object)</span></div>`;
            }
            let itemsHtml = keys.map(k => buildJsonTree(val[k], k, false)).join('');
            return `
                <details class="json-tree-details" ${isRoot ? 'open' : ''}>
                    <summary class="json-summary">
                        ${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span class="json-meta">Object {${keys.length}}</span>
                    </summary>
                    <div class="json-tree-children">
                        ${itemsHtml}
                    </div>
                </details>
            `;
        }
        return `<div class="json-item">${key !== null ? `<span class="json-key">${escapeHtml(key)}:</span> ` : ''}<span>${escapeHtml(String(val))}</span></div>`;
    }

    // Initialize all JSON tree containers
    document.querySelectorAll('.json-tree-container').forEach(container => {
        const rawData = container.getAttribute('data-json');
        if (!rawData) {
            container.innerHTML = '<span class="text-muted fst-italic">No data available</span>';
            return;
        }

        try {
            const parsed = typeof rawData === 'string' ? JSON.parse(rawData) : rawData;
            container.innerHTML = buildJsonTree(parsed, null, true);
        } catch (e) {
            // Not valid JSON or simple text, display as string safely
            container.innerHTML = `<div class="json-item text-break">${escapeHtml(rawData)}</div>`;
        }
    });
});
</script>
@endpush
@endsection
