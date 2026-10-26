@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 750px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Create Master Prompt</h4>
        <a href="{{ route('admin.master-prompts.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('admin.master-prompts.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Prompt Title</label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" placeholder="e.g. Standard Corporate AI Assistant" value="{{ old('title') }}" required>
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Context or internal purpose of this prompt" value="{{ old('description') }}">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">System Prompt Content</label>
                    <textarea name="content" rows="10" class="form-control font-monospace @error('content') is-invalid @enderror" placeholder="You are Company AI Agent..." required>{{ old('content') }}</textarea>
                    @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Set as active master prompt</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Save Master Prompt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
