@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Register HTTP MCP Server</h4>
        <a href="{{ route('admin.mcp-servers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('admin.mcp-servers.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Server Name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Internal CRM MCP Server" value="{{ old('name') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Endpoint URL</label>
                    <input type="url" name="endpoint_url" class="form-control @error('endpoint_url') is-invalid @enderror" placeholder="https://mcp.internal.company.com/rpc" value="{{ old('endpoint_url') }}" required>
                    <small class="text-muted">The HTTP endpoint accepting JSON-RPC 2.0 requests (e.g. tools/list and tools/call).</small>
                    @error('endpoint_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Authentication Type</label>
                        <select name="auth_type" class="form-select" required>
                            <option value="bearer" {{ old('auth_type') === 'bearer' ? 'selected' : '' }}>Bearer Token</option>
                            <option value="none" {{ old('auth_type') === 'none' ? 'selected' : '' }}>None (Open / Internal Network)</option>
                            <option value="custom_header" {{ old('auth_type') === 'custom_header' ? 'selected' : '' }}>Custom Header</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Auth Token / Key</label>
                        <input type="text" name="auth_token" class="form-control font-monospace" placeholder="Token secret..." value="{{ old('auth_token') }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" rows="2" class="form-control" placeholder="What data or services does this MCP server provide?">{{ old('description') }}</textarea>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Enable this MCP Server immediately</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Register & Discover Tools
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
