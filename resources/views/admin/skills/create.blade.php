@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Add AI Skill</h4>
        <a href="{{ route('admin.skills.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('admin.skills.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Skill Name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. IT & Software Support" value="{{ old('name') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Slug <small class="text-muted fw-normal">(Optional, generated from name if left empty)</small></label>
                    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" placeholder="it-software-support" value="{{ old('slug') }}">
                    @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Short Description</label>
                    <input type="text" name="description" class="form-control" placeholder="What domain or capability does this skill address?" value="{{ old('description') }}">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Instructions for AI Agent</label>
                    <textarea name="instructions" rows="6" class="form-control font-monospace @error('instructions') is-invalid @enderror" placeholder="Provide guidelines, business logic, constraints, links, and tone the AI should apply when handling topics covered by this skill." required>{{ old('instructions') }}</textarea>
                    @error('instructions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Enable this skill immediately</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Save Skill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
