@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    <i class="bi bi-cpu-fill me-1"></i> Gemini 3 Suite
                </span>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                    Stateful Reasoning Inspector
                </span>
            </div>
            <h2 class="fw-bold mb-1 mt-2 text-dark dark:text-light">Gemini 3 Diagnostics & Tool Calling Inspector</h2>
            <p class="text-muted mb-0 small">
                Verify Google Gemini API connectivity, function call dispatch, and stateful cryptographic <code>thought_signature</code> round-tripping.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2 text-muted small">
            <span class="badge bg-body-tertiary border text-body py-2 px-3">
                <i class="bi bi-terminal me-1 text-primary"></i> PHP {{ PHP_VERSION }}
            </span>
            <span class="badge bg-body-tertiary border text-body py-2 px-3">
                <i class="bi bi-shield-check me-1 text-success"></i> OpenSSL Ready
            </span>
        </div>
    </div>

    <div class="row g-4">
        <!-- Configuration & Trigger Card -->
        <div class="col-lg-4">
            <div class="card border shadow-sm sticky-top" style="top: 80px; z-index: 10;">
                <div class="card-header bg-body-tertiary py-3">
                    <h5 class="card-title fw-bold mb-0 d-flex align-items-center gap-2 fs-6">
                        <i class="bi bi-sliders2 text-primary"></i> Diagnostic Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('diagnostics.gemini.run') }}" id="diagnosticForm">
                        @csrf

                        <!-- Provider Selection -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Configured Provider</label>
                            <select name="provider_id" id="providerSelect" class="form-select form-select-sm">
                                @forelse($providers as $p)
                                    <option value="{{ $p->id }}" {{ ($defaultProvider && $defaultProvider->id === $p->id) ? 'selected' : '' }}>
                                        {{ $p->name }} ({{ $p->model }}) {{ $p->is_default ? '★ Default' : '' }}
                                    </option>
                                @empty
                                    <option value="">No Gemini provider configured in DB</option>
                                @endforelse
                            </select>
                        </div>

                        <!-- Target Model -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Target Model</label>
                            <select name="model" id="modelSelect" class="form-select form-select-sm">
                                @foreach($models as $modelId => $modelLabel)
                                    <option value="{{ $modelId }}" {{ $modelId === 'gemini-3.8-flash' ? 'selected' : '' }}>
                                        {{ $modelLabel }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text small">Gemini 3 uses stateful thought signatures on function calls.</div>
                        </div>

                        <!-- Thinking Level (Gemini 3) -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Thinking Level</label>
                            <select name="thinking_level" class="form-select form-select-sm">
                                <option value="high" selected>High (Deep Reasoning / Complex Coding)</option>
                                <option value="medium">Medium (Balanced)</option>
                                <option value="low">Low (Fast Responses)</option>
                                <option value="minimal">Minimal (Lowest Latency)</option>
                            </select>
                        </div>

                        <!-- API Key Override -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">API Key (Optional Override)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-key text-muted"></i></span>
                                <input type="password" name="api_key" class="form-control form-control-sm" placeholder="Leave blank to use provider key">
                            </div>
                            <div class="form-text small">Overriding allows testing unconfigured keys directly.</div>
                        </div>

                        <!-- Run Action -->
                        <button type="submit" id="runBtn" class="btn btn-primary w-100 btn-sm py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-play-circle-fill"></i>
                            <span id="btnText">Execute Gemini 3 Diagnostic</span>
                            <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Diagnostic Output & Inspection -->
        <div class="col-lg-8">
            @if(isset($diagnosticReport))
                <!-- Overall Result Banner -->
                @if($diagnosticReport['summary']['all_passed'])
                    <div class="alert alert-success border-success-subtle shadow-sm mb-4 d-flex align-items-start gap-3">
                        <i class="bi bi-check-circle-fill fs-3 text-success mt-1"></i>
                        <div>
                            <h5 class="alert-heading fw-bold mb-1">All Diagnostic Checks Passed Successfully!</h5>
                            <p class="mb-0 small">
                                Gemini model <strong>{{ $diagnosticReport['target_model'] }}</strong> successfully passed basic generation, tool dispatch, and round-trip tool execution with stateful <code>thought_signature</code> coupling.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="alert alert-danger border-danger-subtle shadow-sm mb-4 d-flex align-items-start gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-danger mt-1"></i>
                        <div>
                            <h5 class="alert-heading fw-bold mb-1">Diagnostic Issues Detected</h5>
                            <p class="mb-2 small">One or more verification steps encountered errors:</p>
                            <ul class="mb-2 ps-3 small">
                                @foreach($diagnosticReport['summary']['issues'] as $issue)
                                    <li class="fw-semibold">{{ $issue }}</li>
                                @endforeach
                            </ul>
                            @if(!empty($diagnosticReport['summary']['recommendations']))
                                <hr class="my-2 border-danger-subtle">
                                <div class="small fw-semibold text-danger-emphasis">
                                    <i class="bi bi-lightbulb me-1"></i> Recommended Fixes:
                                    <ul class="mb-0 ps-3 mt-1">
                                        @foreach($diagnosticReport['summary']['recommendations'] as $rec)
                                            <li>{{ $rec }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Check 1: Basic Connectivity -->
                @php $conn = $diagnosticReport['checks']['connectivity']; @endphp
                <div class="card border shadow-sm mb-3">
                    <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2">
                        <div class="d-flex align-items-center gap-2">
                            @if($conn['status'] === 'passed')
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> PASSED</span>
                            @else
                                <span class="badge bg-danger"><i class="bi bi-x-lg"></i> FAILED</span>
                            @endif
                            <span class="fw-bold small">1. Basic Connectivity & Model Ping</span>
                        </div>
                        <span class="badge bg-body-secondary text-body small">{{ $conn['latency_ms'] ?? 0 }} ms</span>
                    </div>
                    <div class="card-body py-3">
                        @if($conn['status'] === 'passed')
                            <div class="small mb-1 text-muted">Model Response:</div>
                            <div class="p-2 rounded bg-body-tertiary border font-monospace small mb-2">
                                {{ $conn['reply'] ?? '' }}
                            </div>
                            @if(!empty($conn['usage']))
                                <div class="text-muted small">
                                    <span class="me-3"><i class="bi bi-arrow-up-right text-primary me-1"></i> Prompt Tokens: {{ $conn['usage']['promptTokenCount'] ?? 0 }}</span>
                                    <span><i class="bi bi-arrow-down-left text-success me-1"></i> Completion Tokens: {{ $conn['usage']['candidatesTokenCount'] ?? 0 }}</span>
                                </div>
                            @endif
                        @else
                            <div class="text-danger small font-monospace">
                                <i class="bi bi-x-circle me-1"></i> {{ $conn['error'] ?? 'Connection Failed' }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Check 2: Function Calling & Thought Signature -->
                @php $tc = $diagnosticReport['checks']['tool_call']; @endphp
                <div class="card border shadow-sm mb-3">
                    <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2">
                        <div class="d-flex align-items-center gap-2">
                            @if($tc['status'] === 'passed')
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> PASSED</span>
                            @else
                                <span class="badge bg-danger"><i class="bi bi-x-lg"></i> FAILED</span>
                            @endif
                            <span class="fw-bold small">2. Function Call & Thought Signature Inspection</span>
                        </div>
                        <span class="badge bg-body-secondary text-body small">{{ $tc['latency_ms'] ?? 0 }} ms</span>
                    </div>
                    <div class="card-body py-3">
                        @if($tc['status'] === 'passed')
                            <div class="row g-2 mb-2">
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <div class="text-muted small">Invoked Tool:</div>
                                        <div class="fw-bold font-monospace text-primary small">{{ $tc['tool_invoked'] }}</div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="p-2 border rounded bg-body-tertiary">
                                        <div class="text-muted small">Thought Signature:</div>
                                        <div class="fw-bold font-monospace small {{ $tc['has_thought_signature'] ? 'text-success' : 'text-danger' }}">
                                            @if($tc['has_thought_signature'])
                                                <i class="bi bi-shield-lock-fill text-success"></i> Captured ({{ $tc['thought_signature_length'] }} bytes)
                                            @else
                                                <i class="bi bi-x-circle-fill text-danger"></i> Missing
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($tc['has_thought_signature'])
                                <div class="small text-muted mb-1">Signature Source: <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $tc['thought_signature_source'] }}</span></div>
                                <div class="p-2 rounded bg-body-tertiary border font-monospace small mb-2 text-truncate" title="{{ $tc['thought_signature_preview'] }}">
                                    {{ $tc['thought_signature_preview'] }}
                                </div>
                            @endif

                            @if($tc['has_reasoning_block'])
                                <div class="small text-muted mb-1">Model Chain-of-Thought (Filtered from public chat):</div>
                                <div class="p-2 rounded bg-body-tertiary border small text-muted fst-italic mb-2">
                                    {{ $tc['reasoning_preview'] }}
                                </div>
                            @endif
                        @else
                            <div class="text-danger small font-monospace">
                                <i class="bi bi-x-circle me-1"></i> {{ $tc['error'] ?? 'Tool Calling Failed' }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Check 3: Multi-Turn Stateful Round-Trip -->
                @php $rt = $diagnosticReport['checks']['round_trip']; @endphp
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2">
                        <div class="d-flex align-items-center gap-2">
                            @if($rt['status'] === 'passed')
                                <span class="badge bg-success"><i class="bi bi-check-lg"></i> PASSED</span>
                            @else
                                <span class="badge bg-danger"><i class="bi bi-x-lg"></i> FAILED</span>
                            @endif
                            <span class="fw-bold small">3. Stateful Round-Trip Verification</span>
                        </div>
                        <span class="badge bg-body-secondary text-body small">{{ $rt['latency_ms'] ?? 0 }} ms</span>
                    </div>
                    <div class="card-body py-3">
                        @if($rt['status'] === 'passed')
                            <div class="alert alert-success border-success-subtle py-2 px-3 small mb-2">
                                <i class="bi bi-check2-all me-1"></i> Google Gemini accepted the returned tool response without error (HTTP {{ $rt['status_code'] }}).
                            </div>
                            <div class="small text-muted mb-1">Synthesized Final Answer:</div>
                            <div class="p-2 rounded bg-body-tertiary border small">
                                {!! nl2br(e($rt['synthesized_response'])) !!}
                            </div>
                        @else
                            <div class="alert alert-danger border-danger-subtle py-2 px-3 small mb-2">
                                <i class="bi bi-exclamation-octagon-fill me-1"></i> HTTP {{ $rt['status_code'] }}: Google Gemini rejected the round-trip payload!
                            </div>
                            <div class="text-danger small font-monospace p-2 rounded bg-body-tertiary border">
                                {{ $rt['error'] ?? 'Unknown Round-Trip Error' }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Expandable Raw Payloads -->
                <div class="card border shadow-sm mb-4">
                    <div class="card-header bg-body-tertiary py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-bold small"><i class="bi bi-code-slash text-primary me-1"></i> Raw Diagnostic Inspection Payloads</span>
                        <button class="btn btn-sm btn-outline-secondary py-0" type="button" data-bs-toggle="collapse" data-bs-target="#rawPayloads">
                            Toggle JSON
                        </button>
                    </div>
                    <div class="collapse" id="rawPayloads">
                        <div class="card-body p-3">
                            <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active small py-1" data-bs-toggle="tab" data-bs-target="#tabReq1">Tool Request</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link small py-1" data-bs-toggle="tab" data-bs-target="#tabRes1">Tool Response</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link small py-1" data-bs-toggle="tab" data-bs-target="#tabReq2">Round-Trip Request</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link small py-1" data-bs-toggle="tab" data-bs-target="#tabRes2">Round-Trip Response</button>
                                </li>
                            </ul>
                            <div class="tab-content font-monospace small" style="max-height: 350px; overflow-y: auto;">
                                <div class="tab-pane fade show active" id="tabReq1">
                                    <pre class="p-2 bg-dark text-light rounded mb-0"><code>{{ json_encode($tc['request_payload'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                </div>
                                <div class="tab-pane fade" id="tabRes1">
                                    <pre class="p-2 bg-dark text-light rounded mb-0"><code>{{ json_encode($tc['raw_response'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                </div>
                                <div class="tab-pane fade" id="tabReq2">
                                    <pre class="p-2 bg-dark text-light rounded mb-0"><code>{{ json_encode($rt['request_payload'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                </div>
                                <div class="tab-pane fade" id="tabRes2">
                                    <pre class="p-2 bg-dark text-light rounded mb-0"><code>{{ json_encode($rt['raw_response'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            @else
                <!-- Empty Placeholder -->
                <div class="card border shadow-sm text-center py-5 px-4 mb-4">
                    <div class="mb-3">
                        <i class="bi bi-cpu fs-1 text-primary opacity-50"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Ready to Run Gemini 3 Diagnostics</h5>
                    <p class="text-muted small mx-auto" style="max-width: 480px;">
                        Click <strong>"Execute Gemini 3 Diagnostic"</strong> on the left panel to test live API connectivity, verify <code>list_projects</code> tool dispatch, and inspect <code>thought_signature</code> persistence across turns.
                    </p>
                </div>
            @endif

            <!-- Recent Gemini Logs Table -->
            <div class="card border shadow-sm">
                <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between py-2">
                    <span class="fw-bold small"><i class="bi bi-clock-history me-1 text-primary"></i> Recent Gemini LLM Calls</span>
                    <span class="badge bg-secondary-subtle text-secondary small">{{ $recentLogs->count() }} records</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Latency</th>
                                <th>Tokens</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                                <tr>
                                    <td>#{{ $log->id }}</td>
                                    <td>
                                        @if($log->status_code === 200)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">HTTP 200</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">HTTP {{ $log->status_code }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->latency_ms }} ms</td>
                                    <td>{{ $log->total_tokens }}</td>
                                    <td class="text-muted">{{ $log->created_at->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No Gemini logs recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('diagnosticForm');
    const btn = document.getElementById('runBtn');
    const text = document.getElementById('btnText');
    const spinner = document.getElementById('btnSpinner');

    if (form && btn) {
        form.addEventListener('submit', function() {
            btn.disabled = true;
            text.textContent = 'Running Diagnostics...';
            spinner.classList.remove('d-none');
        });
    }
});
</script>
@endsection
