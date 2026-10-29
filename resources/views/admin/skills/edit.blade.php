@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Edit Skill: {{ $skill->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.skills.preview', $skill) }}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill" id="previewSkillBtn" title="Preview markdown in a new tab">
                <i class="bi bi-box-arrow-up-right me-1"></i> Preview Markdown
            </a>
            <a href="{{ route('admin.skills.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('admin.skills.update', $skill) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Skill Name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $skill->name) }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Slug</label>
                    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $skill->slug) }}" required>
                    @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="What domain or capability does this skill address?">{{ old('description', $skill->description) }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Instructions for AI Agent</label>
                    <textarea name="instructions" rows="6" class="form-control font-monospace @error('instructions') is-invalid @enderror" required>{{ old('instructions', $skill->instructions) }}</textarea>
                    @error('instructions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', $skill->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Skill is Active</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Update Skill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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
