@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-key text-primary me-2"></i>LLM Providers & API Keys</h4>
            <p class="text-muted small mb-0">Configure API credentials for OpenAI, Anthropic Claude, Google Gemini, OpenRouter, or custom LLM endpoints.</p>
        </div>
        <a href="{{ route('admin.llm-providers.create') }}" class="btn btn-primary rounded-pill btn-sm px-3">
            <i class="bi bi-plus-lg me-1"></i> Add Provider
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-3">Provider Name</th>
                        <th>Type</th>
                        <th>Model</th>
                        <th>Default</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($providers as $provider)
                        <tr>
                            <td class="ps-3 fw-bold">
                                {{ $provider->name }}
                                <div class="small text-muted font-monospace">Key: ••••••••{{ substr($provider->api_key, -4) }}</div>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary">{{ strtoupper($provider->provider_type) }}</span></td>
                            <td><code>{{ $provider->model }}</code></td>
                            <td>
                                @if($provider->is_default)
                                    <span class="badge bg-success">Default Provider</span>
                                @else
                                    <form action="{{ route('admin.llm-providers.set-default', $provider) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2 small">
                                            Set Default
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $provider->is_active ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ $provider->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.llm-providers.logs', $provider) }}" class="btn btn-sm btn-outline-dark rounded-pill me-1" title="View Diagnostic Logs">
                                    <i class="bi bi-journal-code"></i> Logs
                                </a>
                                <form action="{{ route('admin.llm-providers.test', $provider) }}" method="POST" class="d-inline" title="Test Connection">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info rounded-pill me-1">
                                        <i class="bi bi-lightning-charge"></i> Test
                                    </button>
                                </form>
                                <a href="{{ route('admin.llm-providers.edit', $provider) }}" class="btn btn-sm btn-outline-secondary rounded-pill me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.llm-providers.destroy', $provider) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete provider credentials?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No LLM providers configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
