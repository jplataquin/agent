@extends('layouts.app')

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
        <div class="card-header bg-white border-0 pt-3 pb-0">
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

    <!-- Logs Table -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-list-check me-1"></i> Request &amp; Response History ({{ $logs->total() }} entries)
            </h6>
            <small class="text-muted">Showing latest 30 requests</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-3" style="width: 170px;">Timestamp</th>
                        <th>Action</th>
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
                                    <span class="badge bg-light text-dark border">{{ $log->action }}</span>
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
                                <div class="text-truncate font-monospace small" style="max-width: 320px;" title="{{ $log->endpoint }}">
                                    {{ $log->http_method }} {{ $log->endpoint }}
                                </div>
                                @if($log->error_message)
                                    <div class="text-danger small text-truncate mt-1" style="max-width: 380px;" title="{{ $log->error_message }}">
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
                                            <span class="badge bg-light text-dark border ms-1">{{ $log->created_at->toDateTimeString() }}</span>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <!-- Endpoint & Details -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold text-muted small text-uppercase">Endpoint URL</label>
                                            <div class="p-2 bg-light rounded font-monospace small text-break">
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
                                                    @if($log->request_payload)
                                                        <button class="btn btn-sm btn-link text-decoration-none py-0 copy-btn" data-target="req-{{ $log->id }}">
                                                            <i class="bi bi-clipboard"></i> Copy JSON
                                                        </button>
                                                    @endif
                                                </div>
                                                <pre id="req-{{ $log->id }}" class="bg-dark text-light p-3 rounded small font-monospace" style="max-height: 480px; overflow-y: auto;">{{ $log->request_payload ? json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'No payload sent (GET request)' }}</pre>
                                            </div>

                                            <!-- Response Body Column -->
                                            <div class="col-lg-6 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <label class="form-label fw-bold text-muted small text-uppercase mb-0">Raw Response Body</label>
                                                    @if($log->response_body)
                                                        <button class="btn btn-sm btn-link text-decoration-none py-0 copy-btn" data-target="res-{{ $log->id }}">
                                                            <i class="bi bi-clipboard"></i> Copy JSON
                                                        </button>
                                                    @endif
                                                </div>
                                                <pre id="res-{{ $log->id }}" class="bg-dark text-light p-3 rounded small font-monospace" style="max-height: 480px; overflow-y: auto;">@php
                                                    $decoded = json_decode($log->response_body, true);
                                                    echo $decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ($log->response_body ?: 'No response body received.');
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
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-text fs-2 text-muted d-block mb-2"></i>
                                <h6 class="fw-bold">No diagnostic logs recorded for this provider yet.</h6>
                                <p class="small text-muted mb-0">Use the Live Test Prompt above or send a message in AI Chat to record requests and responses.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
});
</script>
@endsection
