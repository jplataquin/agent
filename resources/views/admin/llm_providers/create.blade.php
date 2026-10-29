@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Add LLM Provider Credentials</h4>
            <p class="text-muted small mb-0">Configure Google Gemini, OpenAI, Anthropic Claude, or a custom gateway.</p>
        </div>
        <a href="{{ route('admin.llm-providers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <div id="providerAlert" class="alert alert-info py-2 small mb-3">
                <i class="bi bi-info-circle me-1"></i> <span>Google Gemini selected. Uses your Google AI Studio API key (starts with AIzaSy...).</span>
            </div>

            <form action="{{ route('admin.llm-providers.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Friendly Display Name</label>
                    <input type="text" name="name" id="nameInput" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Google Gemini 1.5 Flash" value="{{ old('name') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Provider Protocol</label>
                        <select name="provider_type" id="providerTypeSelect" class="form-select @error('provider_type') is-invalid @enderror" required>
                            <option value="gemini" {{ old('provider_type', 'gemini') === 'gemini' ? 'selected' : '' }}>Google Gemini</option>
                            <option value="openai" {{ old('provider_type') === 'openai' ? 'selected' : '' }}>OpenAI (Compatible)</option>
                            <option value="anthropic" {{ old('provider_type') === 'anthropic' ? 'selected' : '' }}>Anthropic Claude</option>
                            <option value="openrouter" {{ old('provider_type') === 'openrouter' ? 'selected' : '' }}>OpenRouter</option>
                            <option value="custom" {{ old('provider_type') === 'custom' ? 'selected' : '' }}>Custom Gateway</option>
                        </select>
                        @error('provider_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Model Identifier</label>
                        <div class="input-group mb-2">
                            <select id="modelSelect" class="form-select">
                                <option value="">-- Select a Recommended Model --</option>
                            </select>
                            <button class="btn btn-outline-primary" type="button" id="fetchModelsBtn" title="Fetch live models from provider API using the entered API key">
                                <i class="bi bi-cloud-arrow-down me-1"></i> Fetch Models
                            </button>
                        </div>

                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body-secondary text-muted">Selected / Custom:</span>
                            <input type="text" name="model" id="modelInput" list="modelList" class="form-control font-monospace @error('model') is-invalid @enderror" placeholder="gemini-1.5-flash" value="{{ old('model', 'gemini-1.5-flash') }}" required autocomplete="off">
                        </div>
                        <datalist id="modelList"></datalist>

                        <div id="modelFetchAlert" class="alert py-2 px-3 small mt-2 mb-0 d-none" role="alert"></div>

                        <div class="form-text small" id="modelHelpText">Recommended: gemini-1.5-flash, gemini-2.0-flash, or gemini-1.5-pro</div>
                        @error('model') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">API Secret Key</label>
                    <input type="password" name="api_key" id="apiKeyInput" class="form-control font-monospace @error('api_key') is-invalid @enderror" placeholder="AIzaSy... (Google AI Studio Key)" value="{{ old('api_key') }}" required autocomplete="off">
                    <div class="form-text small" id="apiKeyHelpText">Enter your Google AI Studio API key starting with AIzaSy...</div>
                    @error('api_key') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">API Base URL <small class="text-muted fw-normal" id="baseUrlHint">(Optional, leave blank for default Google Gemini endpoint)</small></label>
                    <input type="url" name="base_url" id="baseUrlInput" class="form-control @error('base_url') is-invalid @enderror" placeholder="https://generativelanguage.googleapis.com" value="{{ old('base_url') }}">
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

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Max Tool Iterations</label>
                        <input type="number" min="1" name="max_tool_iterations" class="form-control" placeholder="No limit" value="{{ old('max_tool_iterations') }}">
                        <div class="form-text small">Leave blank for no limit.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Wrap Up Timeout (s)</label>
                        <input type="number" min="1" name="wrap_up_timeout" class="form-control" placeholder="60" value="{{ old('wrap_up_timeout', 60) }}">
                        <div class="form-text small">Default is 60 seconds.</div>
                    </div>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_default" id="isDefaultSwitch" value="1" {{ old('is_default', true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isDefaultSwitch">Set as Default AI Provider (Chat Agent will use this immediately)</label>
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

        if (nameInput && (!nameInput.value || (isInit && nameInput.value.includes('OpenAI') && val === 'gemini'))) {
            nameInput.placeholder = 'e.g. ' + cfg.name;
        }

        if (modelInput) {
            modelInput.placeholder = cfg.model;
            if (!modelInput.value || (!isInit && (modelInput.value === 'gpt-4o' || modelInput.value === 'gemini-1.5-flash' || modelInput.value === 'claude-3-5-sonnet-20241022'))) {
                modelInput.value = cfg.model;
            }
        }

        if (modelHelpText) modelHelpText.textContent = cfg.modelHelp;
        if (apiKeyInput) apiKeyInput.placeholder = cfg.keyPlaceholder;
        if (apiKeyHelpText) apiKeyHelpText.textContent = cfg.keyHelp;
        if (baseUrlInput) {
            baseUrlInput.placeholder = cfg.baseUrlPlaceholder;
            if ((val === 'gemini' || val === 'anthropic') && baseUrlInput.value.includes('api.openai.com')) {
                baseUrlInput.value = '';
            }
        }
        if (baseUrlHint) baseUrlHint.textContent = cfg.baseUrlHint;

        if (providerAlert) {
            providerAlert.innerHTML = '<i class="bi bi-info-circle me-1"></i> ' + cfg.alert;
            providerAlert.className = 'alert ' + cfg.alertClass + ' py-2 small mb-3';
        }

        populateModelDropdown(val, modelInput.value.trim());
    }

    const fetchModelsBtn = document.getElementById('fetchModelsBtn');
    const modelList = document.getElementById('modelList');
    const modelSelect = document.getElementById('modelSelect');
    const modelFetchAlert = document.getElementById('modelFetchAlert');

    const modelPresets = {
        gemini: [
            { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash (Fast & Versatile)' },
            { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro (Advanced Reasoning)' },
            { id: 'gemini-2.0-flash', name: 'Gemini 2.0 Flash (Next-Gen)' },
            { id: 'gemini-2.5-flash', name: 'Gemini 2.5 Flash' },
        ],
        openai: [
            { id: 'gpt-4o', name: 'GPT-4o (Flagship Multimodal)' },
            { id: 'gpt-4o-mini', name: 'GPT-4o Mini (Fast & Cost-effective)' },
            { id: 'o1-preview', name: 'o1 Preview (Advanced Reasoning)' },
            { id: 'o1-mini', name: 'o1 Mini (Fast STEM Reasoning)' },
            { id: 'gpt-4-turbo', name: 'GPT-4 Turbo' },
            { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo' },
        ],
        anthropic: [
            { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet (v2 Recommended)' },
            { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku (Fast & Accurate)' },
            { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus (Most Powerful)' },
            { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet' },
        ],
        openrouter: [
            { id: 'openai/gpt-4o', name: 'OpenAI: GPT-4o' },
            { id: 'anthropic/claude-3.5-sonnet', name: 'Anthropic: Claude 3.5 Sonnet' },
            { id: 'google/gemini-flash-1.5', name: 'Google: Gemini Flash 1.5' },
            { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Meta: Llama 3.1 70B' },
        ],
        custom: [
            { id: 'custom-model', name: 'Custom Gateway Model' }
        ]
    };

    let cachedFetchedModels = {};

    function populateModelDropdown(providerType, selectedModelId, fetchedModels = null) {
        if (!modelSelect) return;
        modelSelect.innerHTML = '';

        const activeFetched = fetchedModels || cachedFetchedModels[providerType] || null;

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = activeFetched && activeFetched.length > 0
            ? `-- Select from ${activeFetched.length} Live Models --`
            : '-- Select a Recommended Model --';
        modelSelect.appendChild(defaultOption);

        if (activeFetched && activeFetched.length > 0) {
            const liveGroup = document.createElement('optgroup');
            liveGroup.label = `Live Fetched Models (${activeFetched.length})`;
            activeFetched.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name;
                if (m.id === selectedModelId) opt.selected = true;
                liveGroup.appendChild(opt);
            });
            modelSelect.appendChild(liveGroup);
        }

        const presets = modelPresets[providerType] || [];
        if (presets.length > 0) {
            const presetGroup = document.createElement('optgroup');
            presetGroup.label = activeFetched ? 'Standard Recommended Presets' : 'Recommended Models';
            presets.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                if (!activeFetched && p.id === selectedModelId) opt.selected = true;
                presetGroup.appendChild(opt);
            });
            modelSelect.appendChild(presetGroup);
        }

        // Keep datalist synchronized for input autocomplete
        if (modelList) {
            modelList.innerHTML = '';
            const allModels = (activeFetched || []).concat(presets);
            allModels.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name;
                modelList.appendChild(opt);
            });
        }
    }

    if (providerSelect) {
        providerSelect.addEventListener('change', () => {
            if (modelFetchAlert) modelFetchAlert.classList.add('d-none');
            updateFields(false);
        });
        updateFields(true);
    }

    if (modelSelect && modelInput) {
        modelSelect.addEventListener('change', function () {
            if (this.value) {
                modelInput.value = this.value;
                modelInput.dispatchEvent(new Event('input'));
            }
        });

        modelInput.addEventListener('input', function () {
            if (modelSelect) {
                modelSelect.value = this.value;
            }
        });
    }

    if (fetchModelsBtn) {
        fetchModelsBtn.addEventListener('click', async function () {
            const apiKey = apiKeyInput.value.trim();
            if (!apiKey) {
                if (modelFetchAlert) {
                    modelFetchAlert.className = 'alert alert-warning py-2 px-3 small mt-2 mb-0';
                    modelFetchAlert.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Please enter an API Secret Key first before fetching available models.';
                    modelFetchAlert.classList.remove('d-none');
                } else {
                    alert('Please enter an API Secret Key first before fetching available models.');
                }
                apiKeyInput.focus();
                return;
            }

            const originalText = fetchModelsBtn.innerHTML;
            fetchModelsBtn.disabled = true;
            fetchModelsBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Fetching...';
            if (modelFetchAlert) modelFetchAlert.classList.add('d-none');

            try {
                const response = await fetch('{{ route("admin.llm-providers.fetch-models") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        provider_type: providerSelect.value,
                        api_key: apiKey,
                        base_url: baseUrlInput.value.trim() || null
                    })
                });

                const data = await response.json();

                if (data.success && data.models && data.models.length > 0) {
                    cachedFetchedModels[providerSelect.value] = data.models;
                    populateModelDropdown(providerSelect.value, modelInput.value.trim(), data.models);

                    if (modelFetchAlert) {
                        modelFetchAlert.className = 'alert alert-success py-2 px-3 small mt-2 mb-0';
                        modelFetchAlert.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Successfully fetched <strong>${data.models.length}</strong> live models. Select one from the dropdown above or enter custom.`;
                        modelFetchAlert.classList.remove('d-none');
                    }

                    if (modelSelect) {
                        modelSelect.focus();
                    }
                } else {
                    const errorMsg = data.message || 'Could not retrieve models. Please check your API key and base URL.';
                    if (modelFetchAlert) {
                        modelFetchAlert.className = 'alert alert-danger py-2 px-3 small mt-2 mb-0';
                        modelFetchAlert.innerHTML = `<i class="bi bi-exclamation-circle-fill me-1"></i> ${errorMsg}`;
                        modelFetchAlert.classList.remove('d-none');
                    } else {
                        alert('Error: ' + errorMsg);
                    }
                }
            } catch (err) {
                const networkMsg = 'Network or server error while fetching models: ' + err.message;
                if (modelFetchAlert) {
                    modelFetchAlert.className = 'alert alert-danger py-2 px-3 small mt-2 mb-0';
                    modelFetchAlert.innerHTML = `<i class="bi bi-exclamation-circle-fill me-1"></i> ${networkMsg}`;
                    modelFetchAlert.classList.remove('d-none');
                } else {
                    alert(networkMsg);
                }
            } finally {
                fetchModelsBtn.disabled = false;
                fetchModelsBtn.innerHTML = originalText;
            }
        });
    }
});
</script>
@endsection
