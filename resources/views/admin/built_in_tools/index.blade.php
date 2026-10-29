@extends('layouts.app')

@section('content')
<div class="container py-3">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Internal Tools</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-1"><i class="bi bi-terminal text-primary me-2"></i>Internal Tools Tester</h3>
            <p class="text-muted small mb-0">Manually invoke and inspect native internal tools like <code>web_fetch</code>, <code>web_search</code>, or custom scripts.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.all-tools-diagnostic.index') }}" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="bi bi-heart-pulse me-1"></i> All Tools Diagnostic
            </a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Tool Selection & Argument Form -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header border-bottom py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-sliders me-2 text-primary"></i>Tool Execution Request</h6>
                </div>
                <div class="card-body">
                    <form id="toolTestForm">
                        @csrf
                        <div class="mb-3">
                            <label for="toolSelect" class="form-label fw-semibold">Select Internal Tool <span class="text-danger">*</span></label>
                            <select class="form-select" id="toolSelect" name="tool_name" required>
                                @foreach($tools as $tool)
                                    <option value="{{ $tool['name'] }}" {{ $tool['name'] === 'web_fetch' ? 'selected' : '' }}>
                                        {{ $tool['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text" id="toolDescription"></div>
                        </div>

                        <!-- Dynamic Argument Inputs Container -->
                        <div id="argumentsContainer" class="mb-3">
                            <!-- Populated via JavaScript based on selected tool schema -->
                        </div>

                        <!-- Raw JSON Arguments Override Toggle -->
                        <div class="mb-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="rawJsonToggle">
                                <label class="form-check-label small" for="rawJsonToggle">Edit Arguments as Raw JSON</label>
                            </div>
                            <div id="rawJsonWrapper" class="d-none">
                                <textarea id="rawJsonInput" class="form-control font-monospace small" rows="5" placeholder='{"url": "https://example.com", "max_length": 4000}'></textarea>
                                <div class="form-text small">Enter valid JSON matching the input schema.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="button" id="resetBtn" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Fields
                            </button>
                            <button type="submit" id="runTestBtn" class="btn btn-primary px-4">
                                <span id="btnSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                <i class="bi bi-play-fill me-1" id="btnIcon"></i> Execute Tool
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tool Schema Documentation Card -->
            <div class="card border-0 shadow-sm rounded-3 mt-4">
                <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-code-square me-2 text-primary"></i>Input Schema</h6>
                    <span class="badge bg-secondary-subtle text-secondary small" id="schemaBadge">JSON Schema</span>
                </div>
                <div class="card-body p-0">
                    <pre id="schemaDisplay" class="bg-body-secondary p-3 mb-0 rounded-bottom font-monospace small" style="max-height: 250px; overflow-y: auto;"></pre>
                </div>
            </div>
        </div>

        <!-- Tool Output / Results -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 d-flex flex-column">
                <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-file-earmark-code me-2 text-primary"></i>Execution Result</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span id="durationBadge" class="badge bg-info-subtle text-info d-none">0 ms</span>
                        <span id="statusBadge" class="badge bg-secondary-subtle text-secondary">Idle</span>
                    </div>
                </div>
                <div class="card-body d-flex flex-column p-3 flex-grow-1">
                    <div id="emptyResultPlaceholder" class="text-center my-auto py-5 text-muted">
                        <i class="bi bi-terminal fs-1 opacity-50 mb-2 d-block"></i>
                        <p class="mb-0">Run a tool test to inspect stdout, response payload, or error logs.</p>
                    </div>

                    <div id="resultContentWrapper" class="d-none d-flex flex-column flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted fw-semibold" id="resultMeta">Output:</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="copyResultBtn" title="Copy Output">
                                <i class="bi bi-clipboard me-1"></i> Copy
                            </button>
                        </div>
                        <pre id="outputDisplay" class="bg-body-secondary p-3 rounded font-monospace small flex-grow-1 mb-0" style="min-height: 380px; max-height: 600px; overflow-y: auto; white-space: pre-wrap; word-break: break-word;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tools = @json($tools);
    const toolSelect = document.getElementById('toolSelect');
    const toolDescription = document.getElementById('toolDescription');
    const schemaDisplay = document.getElementById('schemaDisplay');
    const argumentsContainer = document.getElementById('argumentsContainer');
    const rawJsonToggle = document.getElementById('rawJsonToggle');
    const rawJsonWrapper = document.getElementById('rawJsonWrapper');
    const rawJsonInput = document.getElementById('rawJsonInput');
    const toolTestForm = document.getElementById('toolTestForm');
    const runTestBtn = document.getElementById('runTestBtn');
    const btnSpinner = document.getElementById('btnSpinner');
    const btnIcon = document.getElementById('btnIcon');
    const resetBtn = document.getElementById('resetBtn');
    const emptyResultPlaceholder = document.getElementById('emptyResultPlaceholder');
    const resultContentWrapper = document.getElementById('resultContentWrapper');
    const outputDisplay = document.getElementById('outputDisplay');
    const statusBadge = document.getElementById('statusBadge');
    const durationBadge = document.getElementById('durationBadge');
    const copyResultBtn = document.getElementById('copyResultBtn');

    function getCurrentTool() {
        return tools.find(t => t.name === toolSelect.value) || tools[0];
    }

    function renderToolUI() {
        const tool = getCurrentTool();
        if (!tool) return;

        toolDescription.textContent = tool.description || '';
        schemaDisplay.textContent = JSON.stringify(tool.inputSchema || {}, null, 2);

        argumentsContainer.innerHTML = '';
        const properties = tool.inputSchema?.properties || {};
        const required = tool.inputSchema?.required || [];

        // Build default values for raw JSON
        const defaultArgs = {};

        Object.keys(properties).forEach(propName => {
            const prop = properties[propName];
            const isReq = required.includes(propName);
            const fieldWrapper = document.createElement('div');
            fieldWrapper.className = 'mb-3';

            const label = document.createElement('label');
            label.className = 'form-label small fw-semibold';
            label.htmlFor = 'arg_' + propName;
            label.innerHTML = `${propName} ${isReq ? '<span class="text-danger">*</span>' : '<span class="text-muted fw-normal">(optional)</span>'}`;
            fieldWrapper.appendChild(label);

            let inputElem;
            if (prop.type === 'string' && (propName === 'script' || (prop.description && prop.description.toLowerCase().includes('script')))) {
                inputElem = document.createElement('textarea');
                inputElem.rows = 4;
                inputElem.className = 'form-control font-monospace small';
                inputElem.placeholder = prop.description || '';
                if (propName === 'script') {
                    inputElem.value = 'print("Hello from Python tool!")';
                }
            } else if (prop.type === 'integer' || prop.type === 'number') {
                inputElem = document.createElement('input');
                inputElem.type = 'number';
                inputElem.className = 'form-control';
                inputElem.placeholder = prop.description || '';
                if (propName === 'max_length') {
                    inputElem.value = 4000;
                }
            } else {
                inputElem = document.createElement('input');
                inputElem.type = 'text';
                inputElem.className = 'form-control';
                inputElem.placeholder = prop.description || '';
                if (propName === 'url') {
                    inputElem.value = 'https://example.com';
                }
            }

            inputElem.id = 'arg_' + propName;
            inputElem.dataset.prop = propName;
            inputElem.dataset.type = prop.type || 'string';
            if (isReq) inputElem.required = true;

            fieldWrapper.appendChild(inputElem);

            if (prop.description) {
                const hint = document.createElement('div');
                hint.className = 'form-text small';
                hint.textContent = prop.description;
                fieldWrapper.appendChild(hint);
            }

            argumentsContainer.appendChild(fieldWrapper);
            defaultArgs[propName] = inputElem.value || '';
        });

        rawJsonInput.value = JSON.stringify(defaultArgs, null, 2);
    }

    toolSelect.addEventListener('change', renderToolUI);

    rawJsonToggle.addEventListener('change', function () {
        if (this.checked) {
            argumentsContainer.classList.add('d-none');
            rawJsonWrapper.classList.remove('d-none');
        } else {
            argumentsContainer.classList.remove('d-none');
            rawJsonWrapper.classList.add('d-none');
        }
    });

    resetBtn.addEventListener('click', renderToolUI);

    copyResultBtn.addEventListener('click', function () {
        if (!outputDisplay.textContent) return;
        navigator.clipboard.writeText(outputDisplay.textContent).then(() => {
            copyResultBtn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i> Copied!';
            setTimeout(() => {
                copyResultBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i> Copy';
            }, 2000);
        });
    });

    toolTestForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const tool = getCurrentTool();
        let payloadArgs = {};

        if (rawJsonToggle.checked) {
            try {
                payloadArgs = JSON.parse(rawJsonInput.value || '{}');
            } catch (err) {
                alert('Invalid JSON in arguments input: ' + err.message);
                return;
            }
        } else {
            const inputs = argumentsContainer.querySelectorAll('[data-prop]');
            inputs.forEach(input => {
                const prop = input.dataset.prop;
                const type = input.dataset.type;
                let val = input.value.trim();

                if (type === 'integer' || type === 'number') {
                    val = val !== '' ? Number(val) : undefined;
                }

                if (val !== undefined && val !== '') {
                    payloadArgs[prop] = val;
                }
            });
        }

        // UI State: Loading
        runTestBtn.disabled = true;
        btnSpinner.classList.remove('d-none');
        btnIcon.classList.add('d-none');
        statusBadge.className = 'badge bg-warning text-dark';
        statusBadge.textContent = 'Executing...';
        durationBadge.classList.add('d-none');

        try {
            const res = await fetch("{{ route('admin.internal-tools.test') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    tool_name: tool.name,
                    arguments: payloadArgs
                })
            });

            const data = await res.json();

            emptyResultPlaceholder.classList.add('d-none');
            resultContentWrapper.classList.remove('d-none');

            durationBadge.textContent = `${data.duration_ms || 0} ms`;
            durationBadge.classList.remove('d-none');

            if (res.ok && data.result && data.result.success !== false) {
                statusBadge.className = 'badge bg-success-subtle text-success';
                statusBadge.textContent = 'Success (200 OK)';
            } else {
                statusBadge.className = 'badge bg-danger-subtle text-danger';
                statusBadge.textContent = 'Failed / Warning';
            }

            outputDisplay.textContent = JSON.stringify(data, null, 2);
        } catch (error) {
            emptyResultPlaceholder.classList.add('d-none');
            resultContentWrapper.classList.remove('d-none');
            statusBadge.className = 'badge bg-danger text-white';
            statusBadge.textContent = 'Request Error';
            outputDisplay.textContent = `Error sending test request: ${error.message}`;
        } finally {
            runTestBtn.disabled = false;
            btnSpinner.classList.add('d-none');
            btnIcon.classList.remove('d-none');
        }
    });

    // Initialize UI
    renderToolUI();
});
</script>
@endsection
