@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2">
            <h4 class="fw-bold mb-0">Edit Skill: {{ $skill->name }}</h4>
            <span class="badge {{ $skill->is_active ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }} rounded-pill">
                {{ $skill->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.skills.preview', $skill) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill" id="previewSkillBtn" title="Preview markdown in a new tab">
                <i class="bi bi-box-arrow-up-right me-1"></i> Preview Markdown
            </a>
            <a href="{{ route('admin.skills.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="skills-editor-wrapper d-flex flex-column flex-lg-row align-items-stretch" id="skillsEditorWrapper">
        <!-- Left Pane: Form / Instructions Textarea -->
        <div class="skills-pane skills-form-pane" id="leftPane" style="width: 58%; min-width: 320px;">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <form action="{{ route('admin.skills.update', $skill) }}" method="POST" id="skillForm" class="d-flex flex-column flex-grow-1">
                        @csrf
                        @method('PUT')
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Skill Name</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $skill->name) }}" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Slug</label>
                                <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $skill->slug) }}" required>
                                @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror" placeholder="What domain or capability does this skill address?">{{ old('description', $skill->description) }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3 flex-grow-1 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold mb-0" for="skillInstructions">Instructions for AI Agent</label>
                                <small class="text-body-secondary">Markdown supported &bull; Click Insert from MCP Explorer</small>
                            </div>
                            <textarea name="instructions" id="skillInstructions" rows="12" class="form-control font-monospace flex-grow-1 @error('instructions') is-invalid @enderror" style="min-height: 280px; resize: vertical;" required placeholder="Provide guidelines, business logic, constraints, links, and tone...">{{ old('instructions', $skill->instructions) }}</textarea>
                            @error('instructions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', $skill->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActiveSwitch">Skill is Active</label>
                        </div>

                        <div class="mt-auto">
                            <button type="submit" class="btn btn-primary rounded-pill py-2 px-4 fw-semibold w-100">
                                <i class="bi bi-check2-circle me-1"></i> Update Skill
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Resizer Handle -->
        <div class="skills-resizer d-none d-lg-flex" id="dragResizer" title="Drag horizontally to resize panes">
            <div class="resizer-bar"></div>
        </div>

        <!-- Right Pane: MCP Explorer Collapsable Tree -->
        <div class="skills-pane skills-mcp-pane flex-grow-1 mt-3 mt-lg-0" id="rightPane" style="min-width: 320px;">
            @include('admin.skills._mcp_explorer', ['groupedTools' => $groupedTools])
        </div>
    </div>
</div>

@include('admin.skills._editor_scripts')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const previewBtn = document.getElementById('previewSkillBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', function (e) {
            e.preventDefault();

            // Submit current form inputs to preview endpoint targeting a new tab
            const tempForm = document.createElement('form');
            tempForm.method = 'POST';
            tempForm.action = this.href;
            tempForm.target = '_blank';

            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            tempForm.appendChild(csrfToken);

            ['name', 'slug', 'description', 'instructions'].forEach(fieldName => {
                const input = document.querySelector(`[name="${fieldName}"]`);
                if (input) {
                    const hidden = document.createElement('textarea');
                    hidden.name = fieldName;
                    hidden.value = input.value;
                    hidden.style.display = 'none';
                    tempForm.appendChild(hidden);
                }
            });

            document.body.appendChild(tempForm);
            tempForm.submit();
            tempForm.remove();
        });
    }
});
</script>
@endpush
