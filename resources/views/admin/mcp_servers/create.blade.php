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
                    <small class="text-muted">The HTTP/SSE endpoint accepting Model Context Protocol requests (e.g. tools/list and tools/call).</small>
                    @error('endpoint_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Authentication Type</label>
                        <select name="auth_type" id="authTypeSelect" class="form-select" required>
                            <option value="bearer" {{ old('auth_type', 'bearer') === 'bearer' ? 'selected' : '' }}>Bearer Token</option>
                            <option value="none" {{ old('auth_type') === 'none' ? 'selected' : '' }}>None (Open / Internal)</option>
                            <option value="custom_header" {{ old('auth_type') === 'custom_header' ? 'selected' : '' }}>Custom Header</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="customHeaderGroup" style="{{ old('auth_type') === 'custom_header' ? '' : 'display: none;' }}">
                        <label class="form-label fw-semibold">Header Name</label>
                        <input type="text" name="custom_header_name" class="form-control font-monospace" placeholder="e.g. X-API-Key" value="{{ old('custom_header_name', 'X-API-Key') }}">
                    </div>
                    <div class="col-md-4" id="authTokenGroup" style="{{ old('auth_type') === 'none' ? 'display: none;' : '' }}">
                        <label class="form-label fw-semibold">Auth Token / Key</label>
                        <input type="text" name="auth_token" class="form-control font-monospace" placeholder="Token or key secret..." value="{{ old('auth_token') }}">
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
                        Register &amp; Discover Tools
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const authTypeSelect = document.getElementById('authTypeSelect');
    const customHeaderGroup = document.getElementById('customHeaderGroup');
    const authTokenGroup = document.getElementById('authTokenGroup');

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
    updateAuthFields();
});
</script>
@endpush
@endsection
