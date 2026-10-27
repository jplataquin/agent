@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 750px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Edit MCP Server: {{ $server->name }}</h4>
        <a href="{{ route('admin.mcp-servers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
    </div>

    <!-- Sync status banner if error or success -->
    <div id="editSyncAlertBox">
        @if($server->last_sync_status === 'error')
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Sync Error:</strong> {{ $server->last_sync_error }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @elseif($server->last_sync_status === 'success')
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                Connected and synced {{ $server->last_sync_at ? $server->last_sync_at->diffForHumans() : 'recently' }} ({{ count($server->tools_cache ?? []) }} tools available).
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <form action="{{ route('admin.mcp-servers.update', $server) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Server Name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $server->name) }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Endpoint URL</label>
                    <input type="url" name="endpoint_url" class="form-control @error('endpoint_url') is-invalid @enderror" value="{{ old('endpoint_url', $server->endpoint_url) }}" required>
                    <small class="text-muted">The HTTP/SSE endpoint accepting Model Context Protocol requests (e.g. tools/list and tools/call).</small>
                    @error('endpoint_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Authentication Type</label>
                        <select name="auth_type" id="authTypeSelect" class="form-select" required>
                            <option value="bearer" {{ old('auth_type', $server->auth_type) === 'bearer' ? 'selected' : '' }}>Bearer Token</option>
                            <option value="none" {{ old('auth_type', $server->auth_type) === 'none' ? 'selected' : '' }}>None (Open / Internal)</option>
                            <option value="custom_header" {{ old('auth_type', $server->auth_type) === 'custom_header' ? 'selected' : '' }}>Custom Header</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="customHeaderGroup" style="{{ old('auth_type', $server->auth_type) === 'custom_header' ? '' : 'display: none;' }}">
                        <label class="form-label fw-semibold">Header Name</label>
                        <input type="text" name="custom_header_name" class="form-control font-monospace" placeholder="e.g. X-API-Key" value="{{ old('custom_header_name', $server->custom_header_name ?: 'X-API-Key') }}">
                    </div>
                    <div class="col-md-4" id="authTokenGroup" style="{{ old('auth_type', $server->auth_type) === 'none' ? 'display: none;' : '' }}">
                        <label class="form-label fw-semibold">Auth Token / Key</label>
                        <input type="text" name="auth_token" class="form-control font-monospace" placeholder="Token or key secret..." value="{{ old('auth_token', $server->auth_token) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" rows="2" class="form-control">{{ old('description', $server->description) }}</textarea>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', $server->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Server is Active</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Update MCP Server &amp; Re-Sync
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cached Tools List -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">Cached Tools (<span id="toolsCountSpan">{{ count($server->tools_cache ?? []) }}</span>)</h6>
            <button type="button" 
                    id="reSyncBtn" 
                    class="btn btn-sm btn-outline-primary rounded-pill" 
                    data-url="{{ route('admin.mcp-servers.sync', $server) }}"
                    title="Query tools/list from endpoint">
                <i class="bi bi-arrow-repeat me-1 sync-icon"></i> <span class="sync-text">Re-Sync Tools</span>
            </button>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush" id="toolsListGroup">
                @forelse($server->tools_cache ?? [] as $tool)
                    <li class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold font-monospace text-primary">{{ $tool['name'] ?? 'Unnamed' }}</span>
                        </div>
                        <p class="small text-muted mb-0">{{ $tool['description'] ?? 'No description provided.' }}</p>
                    </li>
                @empty
                    <li class="list-group-item text-center py-4 text-muted small" id="noToolsPlaceholder">No tools discovered yet. Click Re-Sync Tools to query endpoint.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const authTypeSelect = document.getElementById('authTypeSelect');
    const customHeaderGroup = document.getElementById('customHeaderGroup');
    const authTokenGroup = document.getElementById('authTokenGroup');
    const reSyncBtn = document.getElementById('reSyncBtn');
    const alertBox = document.getElementById('editSyncAlertBox');
    const toolsCountSpan = document.getElementById('toolsCountSpan');
    const toolsListGroup = document.getElementById('toolsListGroup');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function updateAuthFields() {
        const val = authTypeSelect.value;
        if (val === 'none') {
            customHeaderGroup.style.display = 'none';
            authTokenGroup.style.display = 'none';
        } else if (val === 'custom_header') {
            customHeaderGroup.style.display = '';
            authTokenGroup.style.display = '';
        } else {
            customHeaderGroup.style.display = 'none';
            authTokenGroup.style.display = '';
        }
    }

    authTypeSelect.addEventListener('change', updateAuthFields);

    if (reSyncBtn) {
        reSyncBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            const icon = this.querySelector('.sync-icon');
            const text = this.querySelector('.sync-text');

            this.disabled = true;
            this.classList.remove('btn-outline-primary', 'btn-success', 'btn-danger');
            this.classList.add('btn-primary');
            icon.className = 'spinner-border spinner-border-sm me-1';
            text.textContent = 'Syncing...';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-success');
                    icon.className = 'bi bi-check-circle-fill me-1';
                    text.textContent = `Synced (${data.count} tools)`;

                    if (toolsCountSpan) toolsCountSpan.textContent = data.count;

                    // Update list of tools
                    if (toolsListGroup && Array.isArray(data.tools)) {
                        if (data.tools.length === 0) {
                            toolsListGroup.innerHTML = '<li class="list-group-item text-center py-4 text-muted small">No tools discovered from endpoint.</li>';
                        } else {
                            toolsListGroup.innerHTML = data.tools.map(t => `
                                <li class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold font-monospace text-primary">${t.name || 'Unnamed'}</span>
                                    </div>
                                    <p class="small text-muted mb-0">${t.description || 'No description provided.'}</p>
                                </li>
                            `).join('');
                        }
                    }

                    if (alertBox) {
                        alertBox.innerHTML = `
                            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i> Successfully discovered ${data.count} tools from endpoint.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                    }

                    setTimeout(() => {
                        this.disabled = false;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-primary');
                        icon.className = 'bi bi-arrow-repeat me-1 sync-icon';
                        text.textContent = 'Re-Sync Tools';
                    }, 3500);
                } else {
                    const errorMsg = data.error || data.message || 'Failed to sync tools.';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-danger');
                    icon.className = 'bi bi-exclamation-triangle-fill me-1';
                    text.textContent = 'Sync Failed';

                    if (alertBox) {
                        alertBox.innerHTML = `
                            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <strong>Sync Failed:</strong> ${errorMsg}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                    }

                    setTimeout(() => {
                        this.disabled = false;
                        this.classList.remove('btn-danger');
                        this.classList.add('btn-outline-danger');
                        icon.className = 'bi bi-arrow-repeat me-1 sync-icon';
                        text.textContent = 'Retry Sync';
                    }, 3500);
                }
            } catch (err) {
                this.classList.remove('btn-primary');
                this.classList.add('btn-danger');
                icon.className = 'bi bi-wifi-off me-1';
                text.textContent = 'Error';

                if (alertBox) {
                    alertBox.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Network error connecting to sync endpoint.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                }

                setTimeout(() => {
                    this.disabled = false;
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-outline-danger');
                    icon.className = 'bi bi-arrow-repeat me-1 sync-icon';
                    text.textContent = 'Retry Sync';
                }, 3500);
            }
        });
    }
});
</script>
@endpush
@endsection
