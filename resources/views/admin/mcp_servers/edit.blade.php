@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Edit MCP Server: {{ $server->name }}</h4>
        <a href="{{ route('admin.mcp-servers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
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
                    @error('endpoint_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Authentication Type</label>
                        <select name="auth_type" class="form-select" required>
                            <option value="bearer" {{ old('auth_type', $server->auth_type) === 'bearer' ? 'selected' : '' }}>Bearer Token</option>
                            <option value="none" {{ old('auth_type', $server->auth_type) === 'none' ? 'selected' : '' }}>None (Open / Internal)</option>
                            <option value="custom_header" {{ old('auth_type', $server->auth_type) === 'custom_header' ? 'selected' : '' }}>Custom Header</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Auth Token / Key</label>
                        <input type="text" name="auth_token" class="form-control font-monospace" value="{{ old('auth_token', $server->auth_token) }}">
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
                        Update MCP Server
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cached Tools List -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">Cached Tools ({{ count($server->tools_cache ?? []) }})</h6>
            <form action="{{ route('admin.mcp-servers.sync', $server) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
                    <i class="bi bi-arrow-repeat me-1"></i> Re-Sync Tools
                </button>
            </form>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @forelse($server->tools_cache ?? [] as $tool)
                    <li class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold font-monospace text-primary">{{ $tool['name'] ?? 'Unnamed' }}</span>
                        </div>
                        <p class="small text-muted mb-0">{{ $tool['description'] ?? 'No description provided.' }}</p>
                    </li>
                @empty
                    <li class="list-group-item text-center py-4 text-muted small">No tools discovered yet. Click Re-Sync Tools to query endpoint.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
