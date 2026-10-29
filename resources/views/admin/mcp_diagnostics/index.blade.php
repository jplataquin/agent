@extends('layouts.app')

@section('content')
<div class="container py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">MCP Diagnostics</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-1"><i class="bi bi-heart-pulse text-danger me-2"></i>MCP Diagnostics & Health Auditor</h3>
            <p class="text-muted small mb-0">Automated health inspection, runtime version verification, and dependency auditing for built-in and external MCP servers.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="text-muted small d-none d-md-inline" id="lastAuditedText">Audited: {{ $audit['summary']['audited_at_formatted'] }}</span>
            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" id="refreshAuditBtn">
                <span class="spinner-border spinner-border-sm me-1 d-none" id="refreshSpinner" role="status" aria-hidden="true"></span>
                <i class="bi bi-arrow-repeat me-1" id="refreshIcon"></i> Run Full Audit
            </button>
        </div>
    </div>

    <!-- Alert / Toast Container -->
    <div id="diagnosticsAlertContainer"></div>

    <!-- Summary Banner -->
    @php
        $overall = $audit['summary']['overall_status'];
        $bannerClass = match($overall) {
            'healthy' => 'bg-success-subtle border-success text-success-emphasis',
            'degraded' => 'bg-warning-subtle border-warning text-warning-emphasis',
            default => 'bg-danger-subtle border-danger text-danger-emphasis'
        };
        $bannerIcon = match($overall) {
            'healthy' => 'bi-check-circle-fill text-success',
            'degraded' => 'bi-exclamation-triangle-fill text-warning',
            default => 'bi-x-circle-fill text-danger'
        };
        $bannerTitle = match($overall) {
            'healthy' => 'All MCP Subsystems Operational',
            'degraded' => 'Subsystems Degraded / Warnings Detected',
            default => 'Critical Errors in MCP Subsystems'
        };
    @endphp
    <div class="card border rounded-3 mb-4 shadow-sm {{ $bannerClass }}" id="summaryBanner">
        <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <i class="bi {{ $bannerIcon }} fs-2" id="summaryIcon"></i>
                <div>
                    <h5 class="fw-bold mb-0" id="summaryTitle">{{ $bannerTitle }}</h5>
                    <div class="small opacity-75" id="summaryDetails">
                        {{ $audit['summary']['healthy_count'] }} Healthy &bull;
                        {{ $audit['summary']['warning_count'] }} Warnings &bull;
                        {{ $audit['summary']['error_count'] }} Errors
                    </div>
                </div>
            </div>
            <!-- Quick Environment Tags -->
            <div class="d-flex flex-wrap gap-2 small">
                <span class="badge bg-body text-body border px-2 py-1"><i class="bi bi-cpu me-1"></i>PHP {{ $audit['system_environment']['php_version'] }}</span>
                <span class="badge bg-body text-body border px-2 py-1"><i class="bi bi-person me-1"></i>User: {{ $audit['system_environment']['current_user'] }}</span>
                <span class="badge bg-body text-body border px-2 py-1"><i class="bi bi-hdd me-1"></i>OS: {{ $audit['system_environment']['php_os'] }}</span>
                <span class="badge bg-body text-body border px-2 py-1">
                    <i class="bi {{ $audit['system_environment']['temp_dir_writable'] ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' }} me-1"></i>
                    Sandbox: {{ $audit['system_environment']['temp_dir_writable'] ? 'Writable' : 'Read-only' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Subsystems Grid -->
    <div class="row g-4">
        <!-- 1. Built-in Web & System MCP -->
        @php
            $builtin = $audit['subsystems']['builtin_mcp'];
            $builtinBadgeClass = match($builtin['status']) {
                'ok' => 'bg-success-subtle text-success',
                'warning' => 'bg-warning-subtle text-warning',
                default => 'bg-danger-subtle text-danger'
            };
        @endphp
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-terminal text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0">Built-in Web & System MCP</h6>
                    </div>
                    <span class="badge {{ $builtinBadgeClass }}" id="badge_builtin">{{ strtoupper($builtin['status']) }}</span>
                </div>
                <div class="card-body p-3">
                    <div class="small text-muted mb-3">
                        Local JSON-RPC endpoint: <code>{{ $builtin['endpoint'] }}</code>
                    </div>

                    <ul class="list-group list-group-flush mb-3" id="list_builtin_checks">
                        @foreach($builtin['checks'] as $key => $check)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <div>
                                    <div class="fw-semibold small">{{ $check['name'] }}</div>
                                    <div class="text-muted small">{{ $check['message'] }}</div>
                                </div>
                                <span class="badge {{ $check['status'] === 'ok' ? 'bg-success-subtle text-success' : ($check['status'] === 'warning' ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger') }}">
                                    {{ strtoupper($check['status']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-3">
                        <div class="small fw-semibold text-muted mb-2">Declared Built-in Tools:</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($builtin['tools'] as $tool)
                                <span class="badge bg-body-secondary text-body font-monospace small px-2 py-1" title="{{ $tool['description'] ?? '' }}">
                                    {{ $tool['name'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="card-footer border-top py-2 d-flex justify-content-end">
                    <a href="{{ route('admin.built-in-tools.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                        <i class="bi bi-play-circle me-1"></i> Test in Tool Runner
                    </a>
                </div>
            </div>
        </div>

        <!-- 2. Python Code Execution Sandbox -->
        @php
            $python = $audit['subsystems']['python_sandbox'];
            $pythonBadgeClass = match($python['status']) {
                'ok' => 'bg-success-subtle text-success',
                'warning' => 'bg-warning-subtle text-warning',
                default => 'bg-danger-subtle text-danger'
            };
        @endphp
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-filetype-py text-success fs-5"></i>
                        <h6 class="fw-bold mb-0">Python Execution Sandbox</h6>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-info-subtle text-info small" id="mode_python">{{ $python['active_mode'] }}</span>
                        <span class="badge {{ $pythonBadgeClass }}" id="badge_python">{{ strtoupper($python['status']) }}</span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <ul class="list-group list-group-flush mb-3" id="list_python_checks">
                        @foreach($python['checks'] as $key => $check)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <div>
                                    <div class="fw-semibold small">{{ $check['name'] }}</div>
                                    <div class="text-muted small">{{ $check['message'] }}</div>
                                </div>
                                <span class="badge {{ $check['status'] === 'ok' ? 'bg-success-subtle text-success' : ($check['status'] === 'warning' ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger') }}">
                                    {{ strtoupper($check['status']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    @if(!empty($python['remediation']) && $python['status'] !== 'ok')
                        <div class="card bg-body-secondary border-0 rounded-3 p-3 mt-3">
                            <div class="small fw-bold text-uppercase mb-1"><i class="bi bi-wrench me-1"></i>Recommended Setup Commands</div>
                            <div class="font-monospace small bg-body p-2 rounded mb-2 text-break">
                                {{ $python['remediation']['pull_image'] }}
                            </div>
                            <div class="font-monospace small bg-body p-2 rounded text-break">
                                {{ $python['remediation']['install_docker'] }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- 3. Playwright Headless Browser MCP -->
        @php
            $browser = $audit['subsystems']['playwright_browser'];
            $browserBadgeClass = match($browser['status']) {
                'ok' => 'bg-success-subtle text-success',
                'warning' => 'bg-warning-subtle text-warning',
                default => 'bg-danger-subtle text-danger'
            };
        @endphp
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-browser-chrome text-warning fs-5"></i>
                        <h6 class="fw-bold mb-0">Playwright Browser MCP Server</h6>
                    </div>
                    <span class="badge {{ $browserBadgeClass }}" id="badge_browser">{{ strtoupper($browser['status']) }}</span>
                </div>
                <div class="card-body p-3">
                    <div class="small text-muted mb-3 d-flex justify-content-between">
                        <span>Target: <code>{{ $browser['endpoint'] }}</code></span>
                        <span>Health: <code>{{ $browser['health_endpoint'] }}</code></span>
                    </div>

                    <ul class="list-group list-group-flush mb-3" id="list_browser_checks">
                        @foreach($browser['checks'] as $key => $check)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <div>
                                    <div class="fw-semibold small">{{ $check['name'] }}</div>
                                    <div class="text-muted small">{{ $check['message'] }}</div>
                                </div>
                                <span class="badge {{ $check['status'] === 'ok' ? 'bg-success-subtle text-success' : ($check['status'] === 'warning' ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger') }}">
                                    {{ strtoupper($check['status']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    @if($browser['server_reachable'] && !empty($browser['server_data']['browser']))
                        <div class="card bg-body-secondary border-0 rounded-3 p-3 mb-3">
                            <div class="small fw-semibold mb-1"><i class="bi bi-speedometer me-1 text-primary"></i>Live Browser Context</div>
                            <div class="small text-muted">
                                <strong>Active Tab:</strong> {{ $browser['server_data']['browser']['active_page'] ? 'Open' : 'Idle / Closed' }} &bull;
                                <strong>URL:</strong> {{ $browser['server_data']['browser']['current_url'] ?: 'about:blank' }}
                            </div>
                        </div>
                    @endif

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" id="registerPlaywrightBtn">
                            <i class="bi bi-plus-circle me-1"></i> Register / Sync MCP Record
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#systemdModal">
                            <i class="bi bi-gear me-1"></i> Systemd Guide
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Registered External MCP Servers -->
        @php
            $regServers = $audit['subsystems']['registered_servers'];
            $regBadgeClass = match($regServers['status']) {
                'ok' => 'bg-success-subtle text-success',
                'warning' => 'bg-warning-subtle text-warning',
                default => 'bg-danger-subtle text-danger'
            };
        @endphp
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-hdd-network text-info fs-5"></i>
                        <h6 class="fw-bold mb-0">Registered MCP Endpoints</h6>
                    </div>
                    <span class="badge {{ $regBadgeClass }}" id="badge_servers">{{ $regServers['total_servers'] }} Servers</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light text-uppercase">
                                <tr>
                                    <th class="ps-3">Server</th>
                                    <th>Status</th>
                                    <th>Latency</th>
                                    <th>Tools</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody id="table_registered_servers">
                                @forelse($regServers['servers'] as $srv)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold">{{ $srv['name'] }}</div>
                                            <div class="text-muted text-truncate font-monospace" style="max-width: 180px;">{{ $srv['endpoint_url'] }}</div>
                                        </td>
                                        <td>
                                            @if($srv['reachable'])
                                                <span class="badge bg-success-subtle text-success">Online</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger" title="{{ $srv['error_message'] }}">Offline</span>
                                            @endif
                                        </td>
                                        <td>{{ $srv['latency_ms'] ? $srv['latency_ms'] . ' ms' : '-' }}</td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary">{{ $srv['cached_tools_count'] }} cached</span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 sync-server-btn" data-id="{{ $srv['id'] }}" title="Sync Tools">
                                                <i class="bi bi-arrow-repeat"></i> Sync
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No external MCP servers registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer border-top py-2 d-flex justify-content-end">
                    <a href="{{ route('admin.mcp-servers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                        <i class="bi bi-hdd-network me-1"></i> Manage All MCP Servers
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Systemd Configuration Helper -->
<div class="modal fade" id="systemdModal" tabindex="-1" aria-labelledby="systemdModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="systemdModalLabel"><i class="bi bi-terminal me-2"></i>Playwright MCP Systemd Service Guide</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">To keep the Playwright Browser MCP server running continuously on your Ubuntu production server, configure a systemd service:</p>

                <h6 class="fw-bold small">1. Create service file <code>/etc/systemd/system/agent-browser.service</code>:</h6>
                <pre class="bg-body-secondary p-3 rounded font-monospace small mb-3">
[Unit]
Description=Playwright MCP Headless Browser Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory={{ base_path() }}
Environment=NODE_ENV=production
Environment=PORT=3030
Environment=HOST=127.0.0.1
Environment=PLAYWRIGHT_BROWSERS_PATH=/var/www/.cache/ms-playwright
ExecStart=/usr/local/bin/node {{ base_path('playwright-server/server.js') }}
Restart=always
RestartSec=5s

[Install]
WantedBy=multi-user.target
                </pre>

                <h6 class="fw-bold small">2. Enable & start the service:</h6>
                <pre class="bg-body-secondary p-3 rounded font-monospace small mb-3">
sudo systemctl daemon-reload
sudo systemctl enable --now agent-browser.service
sudo systemctl status agent-browser.service
                </pre>

                <h6 class="fw-bold small">3. If node or npx is not in <code>/usr/local/bin</code>:</h6>
                <pre class="bg-body-secondary p-3 rounded font-monospace small mb-0">
sudo ln -sfn $(which node) /usr/local/bin/node
sudo ln -sfn $(which npm) /usr/local/bin/npm
sudo ln -sfn $(which npx) /usr/local/bin/npx
                </pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const refreshBtn = document.getElementById('refreshAuditBtn');
    const refreshSpinner = document.getElementById('refreshSpinner');
    const refreshIcon = document.getElementById('refreshIcon');
    const lastAuditedText = document.getElementById('lastAuditedText');
    const alertContainer = document.getElementById('diagnosticsAlertContainer');

    function showAlert(type, message) {
        alertContainer.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show mb-4 small" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }

    async function runDiagnostics() {
        refreshBtn.disabled = true;
        refreshSpinner.classList.remove('d-none');
        refreshIcon.classList.add('d-none');

        try {
            const res = await fetch("{{ route('admin.mcp-diagnostics.run') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();
            if (res.ok && data.success) {
                lastAuditedText.textContent = 'Audited: ' + data.audit.summary.audited_at_formatted;
                showAlert('success', '<i class="bi bi-check-circle me-1"></i> Diagnostic audit completed successfully.');
                // Update UI without full reload
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showAlert('danger', 'Failed to run audit: ' + (data.message || 'Unknown error'));
            }
        } catch (e) {
            showAlert('danger', 'Error connecting to audit endpoint: ' + e.message);
        } finally {
            refreshBtn.disabled = false;
            refreshSpinner.classList.add('d-none');
            refreshIcon.classList.remove('d-none');
        }
    }

    refreshBtn.addEventListener('click', runDiagnostics);

    // Register Playwright button
    const registerBtn = document.getElementById('registerPlaywrightBtn');
    if (registerBtn) {
        registerBtn.addEventListener('click', async function () {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Registering...';

            try {
                const res = await fetch("{{ route('admin.mcp-diagnostics.action') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: 'register_playwright' })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    showAlert('success', '<i class="bi bi-check-circle me-1"></i> ' + data.message);
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showAlert('danger', 'Registration failed: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                showAlert('danger', 'Request error: ' + e.message);
            } finally {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Register / Sync MCP Record';
            }
        });
    }

    // Sync Server Buttons
    document.querySelectorAll('.sync-server-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const serverId = this.dataset.id;
            this.disabled = true;
            const originalHtml = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const res = await fetch("{{ route('admin.mcp-diagnostics.action') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: 'sync_server', server_id: serverId })
                });

                const data = await res.json();
                if (res.ok && data.success) {
                    showAlert('success', '<i class="bi bi-check-circle me-1"></i> ' + data.message);
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showAlert('danger', data.message || 'Failed to sync tools.');
                }
            } catch (e) {
                showAlert('danger', 'Request error: ' + e.message);
            } finally {
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    });
});
</script>
@endsection
