@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Edit LLM Provider: {{ $provider->name }}</h4>
            <p class="text-muted small mb-0">Update credentials and settings for this model provider.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.llm-providers.logs', $provider) }}" class="btn btn-outline-dark btn-sm rounded-pill">
                <i class="bi bi-journal-code me-1"></i> Diagnostic Logs
            </a>
            <a href="{{ route('admin.llm-providers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <div id="providerAlert" class="alert alert-info py-2 small mb-3">
                <i class="bi bi-info-circle me-1"></i> <span>Configure credentials for the selected AI service.</span>
            </div>

            <form action="{{ route('admin.llm-providers.update', $provider) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Friendly Display Name</label>
                    <input type="text" name="name" id="nameInput" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $provider->name) }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Provider Protocol</label>
                        <select name="provider_type" id="providerTypeSelect" class="form-select @error('provider_type') is-invalid @enderror" required>
                            <option value="gemini" {{ old('provider_type', $provider->provider_type) === 'gemini' ? 'selected' : '' }}>Google Gemini</option>
                            <option value="openai" {{ old('provider_type', $provider->provider_type) === 'openai' ? 'selected' : '' }}>OpenAI (Compatible)</option>
                            <option value="anthropic" {{ old('provider_type', $provider->provider_type) === 'anthropic' ? 'selected' : '' }}>Anthropic Claude</option>
                            <option value="openrouter" {{ old('provider_type', $provider->provider_type) === 'openrouter' ? 'selected' : '' }}>OpenRouter</option>
                            <option value="custom" {{ old('provider_type', $provider->provider_type) === 'custom' ? 'selected' : '' }}>Custom Gateway</option>
                        </select>
                        @error('provider_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Model Identifier</label>
                        <input type="text" name="model" id="modelInput" class="form-control @error('model') is-invalid @enderror" value="{{ old('model', $provider->model) }}" required>
                        <div class="form-text small" id="modelHelpText">e.g. gemini-1.5-flash, gemini-2.0-flash, gpt-4o</div>
                        @error('model') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">API Secret Key</label>
                    <input type="password" name="api_key" id="apiKeyInput" class="form-control font-monospace @error('api_key') is-invalid @enderror" value="{{ old('api_key', $provider->api_key) }}" required autocomplete="off">
                    <div class="form-text small" id="apiKeyHelpText">Enter your API key or secret token</div>
                    @error('api_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">API Base URL <small class="text-muted fw-normal" id="baseUrlHint">(Optional, leave blank for provider default)</small></label>
                    <input type="url" name="base_url" id="baseUrlInput" class="form-control @error('base_url') is-invalid @enderror" value="{{ old('base_url', $provider->base_url) }}">
                    @error('base_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Temperature (0.0 to 2.0)</label>
                        <input type="number" step="0.05" min="0" max="2" name="temperature" class="form-control" value="{{ old('temperature', $provider->temperature) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Max Completion Tokens</label>
                        <input type="number" name="max_tokens" class="form-control" value="{{ old('max_tokens', $provider->max_tokens) }}">
                    </div>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_default" id="isDefaultSwitch" value="1" {{ old('is_default', $provider->is_default) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isDefaultSwitch">Default Provider for Chat Agent</label>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', $provider->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Provider is Active</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Update Credentials
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const providerSelect = document.getElementById('providerTypeSelect');
    const nameInput = document.getElementById('nameInput');
    const modelInput = document.getElementById('modelInput');
    const modelHelpText = document.getElementById('modelHelpText');
    const apiKeyInput = document.getElementById('apiKeyInput');
    const apiKeyHelpText = document.getElementById('apiKeyHelpText');
    const baseUrlInput = document.getElementById('baseUrlInput');
    const baseUrlHint = document.getElementById('baseUrlHint');
    const providerAlert = document.getElementById('providerAlert');

    const configMap = {
        gemini: {
            name: 'Google Gemini 1.5 Flash',
            model: 'gemini-1.5-flash',
            modelHelp: 'Recommended: gemini-1.5-flash, gemini-2.0-flash, or gemini-1.5-pro',
            keyPlaceholder: 'AIzaSy... (Google AI Studio Key)',
            keyHelp: 'Enter your Google AI Studio API key starting with AIzaSy...',
            baseUrlPlaceholder: 'https://generativelanguage.googleapis.com',
            baseUrlHint: '(Optional, leave blank for default Google Gemini endpoint)',
            alert: 'Google Gemini selected. Uses your Google AI Studio API key (starts with AIzaSy...).',
            alertClass: 'alert-info'
        },
        openai: {
            name: 'OpenAI GPT-4o Production',
            model: 'gpt-4o',
            modelHelp: 'Recommended: gpt-4o, gpt-4o-mini, o1',
            keyPlaceholder: 'sk-proj-... or sk-...',
            keyHelp: 'Enter your OpenAI platform secret key starting with sk-...',
            baseUrlPlaceholder: 'https://api.openai.com/v1',
            baseUrlHint: '(Optional, defaults to https://api.openai.com/v1)',
            alert: 'OpenAI selected. Uses your platform.openai.com secret key.',
            alertClass: 'alert-primary'
        },
        anthropic: {
            name: 'Anthropic Claude 3.5 Sonnet',
            model: 'claude-3-5-sonnet-20241022',
            modelHelp: 'Recommended: claude-3-5-sonnet-20241022, claude-3-5-haiku-20241022',
            keyPlaceholder: 'sk-ant-...',
            keyHelp: 'Enter your Anthropic console API key starting with sk-ant-...',
            baseUrlPlaceholder: 'https://api.anthropic.com',
            baseUrlHint: '(Optional, defaults to https://api.anthropic.com)',
            alert: 'Anthropic Claude selected. Uses your console.anthropic.com API key.',
            alertClass: 'alert-warning'
        },
        openrouter: {
            name: 'OpenRouter AI Gateway',
            model: 'google/gemini-2.0-flash-exp:free',
            modelHelp: 'Format: provider/model-name, e.g. google/gemini-2.0-flash-exp:free',
            keyPlaceholder: 'sk-or-...',
            keyHelp: 'Enter your OpenRouter API key.',
            baseUrlPlaceholder: 'https://openrouter.ai/api/v1',
            baseUrlHint: '(Defaults to https://openrouter.ai/api/v1)',
            alert: 'OpenRouter selected. Multi-model routing platform.',
            alertClass: 'alert-secondary'
        },
        custom: {
            name: 'Custom Self-Hosted LLM',
            model: 'llama-3.3-70b-instruct',
            modelHelp: 'Enter your custom vLLM / Ollama model identifier.',
            keyPlaceholder: 'Bearer token or API key',
            keyHelp: 'Enter your gateway authorization token.',
            baseUrlPlaceholder: 'http://localhost:11434/v1',
            baseUrlHint: '(Required for self-hosted OpenAI-compatible server)',
            alert: 'Custom Gateway selected. Enter your self-hosted OpenAI-compatible endpoint URL.',
            alertClass: 'alert-dark'
        }
    };

    function updateFields(isInit) {
        const val = providerSelect.value;
        const cfg = configMap[val] || configMap.gemini;

        if (modelInput) {
            modelInput.placeholder = cfg.model;
            // On type switch, if model still matches another provider's default, update it
            if (!isInit && (modelInput.value === 'gpt-4o' || modelInput.value === 'gemini-1.5-flash' || modelInput.value === 'claude-3-5-sonnet-20241022')) {
                modelInput.value = cfg.model;
            }
        }

        if (modelHelpText) modelHelpText.textContent = cfg.modelHelp;
        if (apiKeyInput) apiKeyInput.placeholder = cfg.keyPlaceholder;
        if (apiKeyHelpText) apiKeyHelpText.textContent = cfg.keyHelp;

        if (baseUrlInput) {
            baseUrlInput.placeholder = cfg.baseUrlPlaceholder;
            // Auto-clear OpenAI base url when switching to Gemini or Claude
            if (!isInit && (val === 'gemini' || val === 'anthropic') && baseUrlInput.value.includes('api.openai.com')) {
                baseUrlInput.value = '';
            }
        }
        if (baseUrlHint) baseUrlHint.textContent = cfg.baseUrlHint;

        if (providerAlert) {
            providerAlert.innerHTML = '<i class="bi bi-info-circle me-1"></i> ' + cfg.alert;
            providerAlert.className = 'alert ' + cfg.alertClass + ' py-2 small mb-3';
        }
    }

    if (providerSelect) {
        providerSelect.addEventListener('change', () => updateFields(false));
        updateFields(true);
    }
});
</script>
@endsection
