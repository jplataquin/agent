@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-hdd-network text-primary me-2"></i>HTTP MCP Servers</h4>
            <p class="text-muted small mb-0">Model Context Protocol endpoints connecting the AI agent to your company databases, wiki, and APIs.</p>
        </div>
        <a href="{{ route('admin.mcp-servers.create') }}" class="btn btn-primary rounded-pill btn-sm px-3">
            <i class="bi bi-plus-lg me-1"></i> Register MCP Server
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-3" style="width: 28%;">Server Name</th>
                        <th style="width: 24%;">Endpoint URL</th>
                        <th style="width: 14%;">Auth</th>
                        <th style="width: 16%;">Discovered Tools</th>
                        <th style="width: 8%;">Status</th>
                        <th class="text-end pe-3" style="width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($servers as $server)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold">{{ $server->name }}</div>
                                @if($server->description)
                                    <div class="small text-muted fw-normal">{{ Str::limit($server->description, 60) }}</div>
                                @endif

                                <!-- Sync status & error notification -->
                                @if($server->last_sync_status === 'error')
                                    <div class="alert alert-danger py-1 px-2 mt-1 mb-0 small" id="sync-error-{{ $server->id }}">
                                        <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>
                                        <span class="sync-error-msg">{{ $server->last_sync_error }}</span>
                                    </div>
                                    <div class="small text-muted mt-1 sync-status-line d-none" id="sync-status-{{ $server->id }}"></div>
                                @elseif($server->last_sync_status === 'success')
                                    <div class="small text-muted mt-1 sync-status-line" id="sync-status-{{ $server->id }}">
                                        <i class="bi bi-check2-circle text-success me-1"></i> Connected &amp; Synced {{ $server->last_sync_at ? $server->last_sync_at->diffForHumans() : 'recently' }}
                                    </div>
                                    <div class="alert alert-danger py-1 px-2 mt-1 mb-0 small d-none" id="sync-error-{{ $server->id }}">
                                        <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>
                                        <span class="sync-error-msg"></span>
                                    </div>
                                @else
                                    <div class="small text-muted mt-1 sync-status-line" id="sync-status-{{ $server->id }}">
                                        <i class="bi bi-clock text-secondary me-1"></i> Not synced yet
                                    </div>
                                    <div class="alert alert-danger py-1 px-2 mt-1 mb-0 small d-none" id="sync-error-{{ $server->id }}">
                                        <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>
                                        <span class="sync-error-msg"></span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <code>{{ $server->endpoint_url }}</code>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">{{ strtoupper($server->auth_type) }}</span>
                                @if($server->auth_type === 'custom_header')
                                    <div class="small text-muted font-monospace mt-1">{{ $server->custom_header_name ?: 'X-API-Key' }}</div>
                                @endif
                            </td>
                            <td>
                                @php $toolsCount = count($server->tools_cache ?? []); @endphp
                                <button type="button" class="btn btn-sm p-0 border-0 text-start" data-bs-toggle="modal" data-bs-target="#toolsModal-{{ $server->id }}" title="Click to view discovered tools">
                                    <span class="badge tools-count-badge {{ $toolsCount > 0 ? 'bg-primary' : 'bg-warning text-dark' }}" id="tools-badge-{{ $server->id }}" style="cursor: pointer;">
                                        {{ $toolsCount }} {{ Str::plural('tool', $toolsCount) }} <i class="bi bi-eye ms-1"></i>
                                    </span>
                                </button>
                                @if($toolsCount > 0)
                                    <div class="small text-muted mt-1">
                                        <span class="font-monospace text-truncate d-inline-block" style="max-width: 150px;" title="{{ implode(', ', array_column($server->tools_cache ?? [], 'name')) }}">
                                            {{ implode(', ', array_slice(array_column($server->tools_cache ?? [], 'name'), 0, 2)) }}{{ $toolsCount > 2 ? '...' : '' }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.mcp-servers.toggle', $server) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm badge {{ $server->is_active ? 'bg-success' : 'bg-secondary' }} border-0">
                                        {{ $server->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-3">
                                <!-- Interactive Sync Button with Feedback -->
                                <button type="button" 
                                        class="btn btn-sm btn-outline-info rounded-pill sync-mcp-btn me-1" 
                                        data-url="{{ route('admin.mcp-servers.sync', $server) }}" 
                                        data-id="{{ $server->id }}"
                                        title="Query tools from endpoint">
                                    <i class="bi bi-arrow-repeat sync-icon me-1"></i><span class="sync-label">Sync</span>
                                </button>

                                <a href="{{ route('admin.mcp-servers.edit', $server) }}" class="btn btn-sm btn-outline-secondary rounded-pill me-1" title="Edit Server">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.mcp-servers.destroy', $server) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete MCP server {{ $server->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Delete Server">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Modal for Inspecting Discovered Tools -->
                        <div class="modal fade" id="toolsModal-{{ $server->id }}" tabindex="-1" aria-labelledby="toolsModalLabel-{{ $server->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content border-0 shadow">
                                    <div class="modal-header bg-light">
                                        <h5 class="modal-title fw-bold" id="toolsModalLabel-{{ $server->id }}">
                                            <i class="bi bi-tools text-primary me-2"></i>{{ $server->name }} &mdash; Discovered Tools
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded-3 small">
                                            <div>
                                                <span class="text-muted">Endpoint:</span> <code>{{ $server->endpoint_url }}</code>
                                            </div>
                                            <div>
                                                <span class="badge bg-secondary-subtle text-secondary font-monospace">{{ strtoupper($server->auth_type) }}</span>
                                            </div>
                                        </div>

                                        <div class="tools-list-container" id="modal-tools-list-{{ $server->id }}">
                                            @if(count($server->tools_cache ?? []) > 0)
                                                <div class="list-group">
                                                    @foreach($server->tools_cache as $tool)
                                                        <div class="list-group-item p-3 border rounded-3 mb-2">
                                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                                <span class="fw-bold font-monospace text-primary fs-6">{{ $tool['name'] ?? 'Unnamed' }}</span>
                                                            </div>
                                                            <p class="small text-muted mb-2">{{ $tool['description'] ?? 'No description provided.' }}</p>
                                                            @php
                                                                $schema = $tool['inputSchema'] ?? $tool['input_schema'] ?? $tool['parameters'] ?? null;
                                                                $props = $schema['properties'] ?? [];
                                                            @endphp
                                                            @if(is_array($props) && count($props) > 0)
                                                                <div class="small bg-light p-2 rounded border">
                                                                    <span class="fw-semibold text-secondary">Parameters:</span>
                                                                    <ul class="mb-0 ps-3 mt-1 font-monospace" style="font-size: 0.8rem;">
                                                                        @foreach($props as $propName => $propDef)
                                                                            <li>
                                                                                <strong>{{ $propName }}</strong>
                                                                                <span class="text-muted">({{ $propDef['type'] ?? 'any' }})</span>:
                                                                                {{ $propDef['description'] ?? 'No description' }}
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="text-center py-4 text-muted">
                                                    <i class="bi bi-tools fs-1 d-block mb-2 text-secondary"></i>
                                                    <p class="mb-1">No tools discovered yet.</p>
                                                    <small>Click <strong>Sync</strong> to query the endpoint and discover available tools.</small>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light">
                                        <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No MCP servers registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    document.querySelectorAll('.sync-mcp-btn').forEach(btn => {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            const id = this.getAttribute('data-id');
            const label = this.querySelector('.sync-label');
            const icon = this.querySelector('.sync-icon');
            const toolsBadge = document.getElementById(`tools-badge-${id}`);
            const errorContainer = document.getElementById(`sync-error-${id}`);
            const statusContainer = document.getElementById(`sync-status-${id}`);
            const modalToolsList = document.getElementById(`modal-tools-list-${id}`);

            // Loading state on button
            this.disabled = true;
            this.classList.remove('btn-outline-info', 'btn-outline-danger', 'btn-success', 'btn-danger');
            this.classList.add('btn-info', 'text-white');
            icon.className = 'spinner-border spinner-border-sm me-1';
            label.textContent = 'Syncing...';

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
                    // Success state
                    this.classList.remove('btn-info', 'text-white');
                    this.classList.add('btn-success');
                    icon.className = 'bi bi-check-circle-fill me-1';
                    label.textContent = `Synced (${data.count})`;

                    // Update tools count badge
                    if (toolsBadge) {
                        toolsBadge.innerHTML = `${data.count} ${data.count === 1 ? 'tool' : 'tools'} <i class="bi bi-eye ms-1"></i>`;
                        toolsBadge.className = `badge tools-count-badge ${data.count > 0 ? 'bg-primary' : 'bg-warning text-dark'}`;
                    }

                    // Update modal content dynamically
                    if (modalToolsList && Array.isArray(data.tools)) {
                        if (data.tools.length === 0) {
                            modalToolsList.innerHTML = `
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-tools fs-1 d-block mb-2 text-secondary"></i>
                                    <p class="mb-1">No tools discovered from endpoint.</p>
                                </div>
                            `;
                        } else {
                            modalToolsList.innerHTML = `<div class="list-group">` + data.tools.map(t => {
                                const schema = t.inputSchema || t.input_schema || t.parameters || {};
                                const props = schema.properties || {};
                                const propKeys = Object.keys(props);
                                let propsHtml = '';
                                if (propKeys.length > 0) {
                                    propsHtml = `
                                        <div class="small bg-light p-2 rounded border mt-2">
                                            <span class="fw-semibold text-secondary">Parameters:</span>
                                            <ul class="mb-0 ps-3 mt-1 font-monospace" style="font-size: 0.8rem;">
                                                ${propKeys.map(k => `<li><strong>${k}</strong> <span class="text-muted">(${props[k].type || 'any'})</span>: ${props[k].description || ''}</li>`).join('')}
                                            </ul>
                                        </div>
                                    `;
                                }
                                return `
                                    <div class="list-group-item p-3 border rounded-3 mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold font-monospace text-primary fs-6">${t.name || 'Unnamed'}</span>
                                        </div>
                                        <p class="small text-muted mb-0">${t.description || 'No description provided.'}</p>
                                        ${propsHtml}
                                    </div>
                                `;
                            }).join('') + `</div>`;
                        }
                    }

                    // Hide any active error box
                    if (errorContainer) {
                        errorContainer.classList.add('d-none');
                    }

                    // Update status line
                    if (statusContainer) {
                        statusContainer.classList.remove('d-none');
                        statusContainer.innerHTML = `<i class="bi bi-check2-circle text-success me-1"></i> Connected &amp; Synced just now`;
                    }

                    // Reset button after 3.5 seconds
                    setTimeout(() => {
                        this.disabled = false;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-info');
                        icon.className = 'bi bi-arrow-repeat sync-icon me-1';
                        label.textContent = 'Sync';
                    }, 3500);
                } else {
                    // Error state
                    const errorMsg = data.error || data.message || 'Failed to sync tools.';
                    this.classList.remove('btn-info', 'text-white');
                    this.classList.add('btn-danger');
                    icon.className = 'bi bi-exclamation-triangle-fill me-1';
                    label.textContent = 'Failed';

                    // Show error box
                    if (errorContainer) {
                        errorContainer.classList.remove('d-none');
                        const msgSpan = errorContainer.querySelector('.sync-error-msg');
                        if (msgSpan) msgSpan.textContent = errorMsg;
                    }
                    if (statusContainer) {
                        statusContainer.classList.add('d-none');
                    }

                    // Reset button to Re-Sync
                    setTimeout(() => {
                        this.disabled = false;
                        this.classList.remove('btn-danger');
                        this.classList.add('btn-outline-danger');
                        icon.className = 'bi bi-arrow-repeat sync-icon me-1';
                        label.textContent = 'Re-Sync';
                    }, 3500);
                }
            } catch (err) {
                // Network error
                this.classList.remove('btn-info', 'text-white');
                this.classList.add('btn-danger');
                icon.className = 'bi bi-wifi-off me-1';
                label.textContent = 'Error';

                if (errorContainer) {
                    errorContainer.classList.remove('d-none');
                    const msgSpan = errorContainer.querySelector('.sync-error-msg');
                    if (msgSpan) msgSpan.textContent = 'Network or server communication error.';
                }

                setTimeout(() => {
                    this.disabled = false;
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-outline-danger');
                    icon.className = 'bi bi-arrow-repeat sync-icon me-1';
                    label.textContent = 'Re-Sync';
                }, 3500);
            }
        });
    });
});
</script>
@endpush
@endsection
