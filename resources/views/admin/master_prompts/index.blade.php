@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-file-text text-primary me-2"></i>Master Prompts</h4>
            <p class="text-muted small mb-0">Base persona and instructions that govern the company AI Agent's global demeanor and security guidelines.</p>
        </div>
        <a href="{{ route('admin.master-prompts.create') }}" class="btn btn-primary rounded-pill btn-sm px-3">
            <i class="bi bi-plus-lg me-1"></i> New Prompt
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-3">Prompt Title</th>
                        <th>Excerpt</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($prompts as $prompt)
                        <tr>
                            <td class="ps-3 fw-bold">{{ $prompt->title }}</td>
                            <td class="small text-muted">{{ Str::limit($prompt->content, 90) }}</td>
                            <td>
                                @if($prompt->is_active)
                                    <span class="badge bg-success">Active Default</span>
                                @else
                                    <form action="{{ route('admin.master-prompts.set-active', $prompt) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2 small">
                                            Make Active
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.master-prompts.edit', $prompt) }}" class="btn btn-sm btn-outline-secondary rounded-pill me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.master-prompts.destroy', $prompt) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this prompt?');">
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
                            <td colspan="4" class="text-center py-4 text-muted">No master prompts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
