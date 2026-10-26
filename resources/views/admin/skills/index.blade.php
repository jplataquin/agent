@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-tools text-primary me-2"></i>AI Skills</h4>
            <p class="text-muted small mb-0">Skills injected into the AI system prompt to guide responses for specific business domains.</p>
        </div>
        <a href="{{ route('admin.skills.create') }}" class="btn btn-primary rounded-pill btn-sm px-3">
            <i class="bi bi-plus-lg me-1"></i> Add Skill
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-3">Skill Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($skills as $skill)
                        <tr>
                            <td class="ps-3 fw-bold">{{ $skill->name }}</td>
                            <td><code>{{ $skill->slug }}</code></td>
                            <td class="small text-muted">{{ Str::limit($skill->description, 60) }}</td>
                            <td>
                                <form action="{{ route('admin.skills.toggle', $skill) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm badge {{ $skill->is_active ? 'bg-success' : 'bg-secondary' }} border-0">
                                        {{ $skill->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.skills.edit', $skill) }}" class="btn btn-sm btn-outline-secondary rounded-pill me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.skills.destroy', $skill) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete skill {{ $skill->name }}?');">
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
                            <td colspan="5" class="text-center py-4 text-muted">No skills configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
