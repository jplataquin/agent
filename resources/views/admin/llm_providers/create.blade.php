@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Add LLM Provider Credentials</h4>
        <a href="{{ route('admin.llm-providers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('admin.llm-providers.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Friendly Display Name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. OpenAI GPT-4o Production" value="{{ old('name') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Provider Protocol</label>
                        <select name="provider_type" class="form-select @error('provider_type') is-invalid @enderror" required>
                            <option value="openai" {{ old('provider_type') === 'openai' ? 'selected' : '' }}>OpenAI (Compatible)</option>
                            <option value="anthropic" {{ old('provider_type') === 'anthropic' ? 'selected' : '' }}>Anthropic Claude</option>
                            <option value="gemini" {{ old('provider_type') === 'gemini' ? 'selected' : '' }}>Google Gemini</option>
                            <option value="openrouter" {{ old('provider_type') === 'openrouter' ? 'selected' : '' }}>OpenRouter</option>
                            <option value="custom" {{ old('provider_type') === 'custom' ? 'selected' : '' }}>Custom Gateway</option>
                        </select>
                        @error('provider_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Model Identifier</label>
                        <input type="text" name="model" class="form-control @error('model') is-invalid @enderror" placeholder="gpt-4o, claude-3-5-sonnet, gemini-1.5-pro" value="{{ old('model', 'gpt-4o') }}" required>
                        @error('model') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">API Secret Key</label>
                    <input type="password" name="api_key" class="form-control font-monospace @error('api_key') is-invalid @enderror" placeholder="sk-..." value="{{ old('api_key') }}" required>
                    @error('api_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">API Base URL <small class="text-muted fw-normal">(Optional, defaults to https://api.openai.com/v1)</small></label>
                    <input type="url" name="base_url" class="form-control @error('base_url') is-invalid @enderror" placeholder="https://api.openai.com/v1" value="{{ old('base_url') }}">
                    @error('base_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Temperature (0.0 to 2.0)</label>
                        <input type="number" step="0.05" min="0" max="2" name="temperature" class="form-control" value="{{ old('temperature', 0.70) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Max Completion Tokens</label>
                        <input type="number" name="max_tokens" class="form-control" placeholder="2048" value="{{ old('max_tokens', 2048) }}">
                    </div>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_default" id="isDefaultSwitch" value="1" {{ old('is_default') ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isDefaultSwitch">Set as Default AI Provider</label>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Enable this Provider</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Save Credentials
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
