@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 750px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Edit Master Prompt: {{ $prompt->title }}</h4>
        <a href="{{ route('admin.master-prompts.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('admin.master-prompts.update', $prompt) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">Prompt Title</label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $prompt->title) }}" required>
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', $prompt->description) }}">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">System Prompt Content</label>
                    <textarea name="content" rows="10" class="form-control font-monospace @error('content') is-invalid @enderror" required>{{ old('content', $prompt->content) }}</textarea>
                    @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', $prompt->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Active Master Prompt</label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Update Master Prompt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
