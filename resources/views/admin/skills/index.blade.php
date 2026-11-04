@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-tools text-primary me-2"></i>AI Skills</h4>
            <p class="text-muted small mb-0">Skills injected into the AI system prompt to guide responses for specific business domains.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm px-3" data-bs-toggle="modal" data-bs-target="#categoriesModal">
                <i class="bi bi-folder2-open me-1"></i> Categories
            </button>
            <button type="button" class="btn btn-outline-danger rounded-pill btn-sm px-3" id="runDoctorBtn" data-bs-toggle="modal" data-bs-target="#doctorModal">
                <i class="bi bi-heart-pulse me-1"></i> Doctor
            </button>
            <a href="{{ route('admin.skills.create') }}" class="btn btn-primary rounded-pill btn-sm px-3">
                <i class="bi bi-plus-lg me-1"></i> Add Skill
            </a>
        </div>
    </div>

    <!-- Category Filter Tabs -->
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <span class="small text-muted fw-semibold me-1"><i class="bi bi-funnel me-1"></i>Category:</span>
        <a href="{{ route('admin.skills.index') }}" class="btn btn-sm rounded-pill {{ !request()->filled('category') ? 'btn-primary' : 'btn-outline-secondary' }}">
            All <span class="badge bg-body-secondary text-body ms-1">{{ $totalSkillsCount }}</span>
        </a>
        @foreach($categories as $cat)
            <a href="{{ route('admin.skills.index', ['category' => $cat->slug]) }}" class="btn btn-sm rounded-pill {{ request()->query('category') === $cat->slug ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $cat->name }} <span class="badge {{ request()->query('category') === $cat->slug ? 'bg-body-secondary text-body' : 'bg-secondary-subtle text-secondary' }} ms-1">{{ $cat->skills_count }}</span>
            </a>
        @endforeach
        @if($uncategorizedCount > 0 || request()->query('category') === 'uncategorized')
            <a href="{{ route('admin.skills.index', ['category' => 'uncategorized']) }}" class="btn btn-sm rounded-pill {{ request()->query('category') === 'uncategorized' ? 'btn-primary' : 'btn-outline-secondary' }}">
                Uncategorized <span class="badge {{ request()->query('category') === 'uncategorized' ? 'bg-body-secondary text-body' : 'bg-secondary-subtle text-secondary' }} ms-1">{{ $uncategorizedCount }}</span>
            </a>
        @endif
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-3">Skill Name</th>
                        <th>Category</th>
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
                            <td>
                                @if($skill->category)
                                    <a href="{{ route('admin.skills.index', ['category' => $skill->category->slug]) }}" class="text-decoration-none">
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                            <i class="bi bi-folder me-1"></i>{{ $skill->category->name }}
                                        </span>
                                    </a>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">General</span>
                                @endif
                            </td>
                            <td><code>{{ $skill->slug }}</code></td>
                            <td class="small text-muted">{{ Str::limit($skill->description, 50) }}</td>
                            <td>
                                <form action="{{ route('admin.skills.toggle', $skill) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm badge {{ $skill->is_active ? 'bg-success' : 'bg-secondary' }} border-0">
                                        {{ $skill->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.skills.edit', $skill) }}" class="btn btn-sm btn-outline-secondary rounded-pill me-1" title="Edit skill">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.skills.destroy', $skill) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete skill {{ $skill->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Delete skill">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No skills found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($skills->hasPages())
            <div class="card-footer bg-transparent border-top py-3">
                {{ $skills->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Categories Modal -->
<div class="modal fade" id="categoriesModal" tabindex="-1" aria-labelledby="categoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="categoriesModalLabel">
                    <i class="bi bi-folder2-open text-primary me-2"></i>Skill Categories
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Add Category Card -->
                <div class="card border-0 bg-body-secondary rounded-3 p-3 mb-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-plus-circle me-1 text-primary"></i> Add New Category</h6>
                    <form action="{{ route('admin.skill-categories.store') }}" method="POST">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-5">
                                <input type="text" name="name" class="form-control form-control-sm" placeholder="Category Name (e.g. Support)" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="slug" class="form-control form-control-sm" placeholder="Slug (optional)">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill w-100">
                                    <i class="bi bi-plus-lg me-1"></i> Add
                                </button>
                            </div>
                            <div class="col-12 mt-2">
                                <input type="text" name="description" class="form-control form-control-sm" placeholder="Brief description (optional)">
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Existing Categories List -->
                <h6 class="fw-bold mb-3">Existing Categories ({{ $categories->count() }})</h6>
                <div class="list-group">
                    @forelse($categories as $cat)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div class="me-3">
                                <div class="fw-bold d-flex align-items-center gap-2">
                                    <span>{{ $cat->name }}</span>
                                    <code class="small text-muted">{{ $cat->slug }}</code>
                                    <span class="badge bg-secondary-subtle text-secondary">{{ $cat->skills_count }} {{ Str::plural('skill', $cat->skills_count) }}</span>
                                </div>
                                @if($cat->description)
                                    <div class="small text-muted mt-1">{{ $cat->description }}</div>
                                @endif
                            </div>
                            <div class="d-flex gap-1 align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="collapse" data-bs-target="#editCategory-{{ $cat->id }}" title="Edit category">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route('admin.skill-categories.destroy', $cat) }}" method="POST" onsubmit="return confirm('Delete category {{ $cat->name }}? Associated skills will become uncategorized.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Delete category">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="collapse p-3 bg-body-tertiary border-start border-end border-bottom" id="editCategory-{{ $cat->id }}">
                            <form action="{{ route('admin.skill-categories.update', $cat) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="row g-2">
                                    <div class="col-md-5">
                                        <label class="small text-muted mb-1">Name</label>
                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $cat->name }}" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small text-muted mb-1">Slug</label>
                                        <input type="text" name="slug" class="form-control form-control-sm" value="{{ $cat->slug }}">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-sm btn-success rounded-pill w-100">
                                            <i class="bi bi-check-lg me-1"></i> Save
                                        </button>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="small text-muted mb-1">Description</label>
                                        <input type="text" name="description" class="form-control form-control-sm" value="{{ $cat->description }}">
                                    </div>
                                </div>
                            </form>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted border rounded">
                            <i class="bi bi-folder-x fs-3 d-block mb-2"></i>
                            No categories created yet. Add one above to organize your skills.
                        </div>
                    @endforelse
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Doctor Modal -->
<div class="modal fade" id="doctorModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="doctorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="doctorModalLabel">
                    <i class="bi bi-heart-pulse text-danger me-2"></i>System Coherence Doctor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" title="Close modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="doctorLoading" class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted small mb-0">Analyzing system prompts, skills, and tools with the active LLM...</p>
                </div>
                <div id="doctorError" class="alert alert-danger d-none" role="alert"></div>
                <div id="doctorResult" class="d-none"></div>
            </div>
            <div class="modal-footer border-top d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill d-none" id="doctorCopyBtn" title="Copy raw markdown report">
                        <i class="bi bi-clipboard me-1"></i> Copy Report
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill d-none" id="doctorOpenFullBtn" title="Open full-page report in a new tab">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Open in New Tab
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal" title="Close modal">
                        <i class="bi bi-x-lg me-1"></i> Close
                    </button>
                    <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" id="doctorRerunBtn">
                        <i class="bi bi-arrow-repeat me-1"></i> Re-run Analysis
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const doctorModal = document.getElementById('doctorModal');
    const doctorLoading = document.getElementById('doctorLoading');
    const doctorError = document.getElementById('doctorError');
    const doctorResult = document.getElementById('doctorResult');
    const doctorRerunBtn = document.getElementById('doctorRerunBtn');
    const doctorCopyBtn = document.getElementById('doctorCopyBtn');
    const doctorOpenFullBtn = document.getElementById('doctorOpenFullBtn');
    let currentDoctorReport = '';

    async function runDoctor() {
        doctorLoading.classList.remove('d-none');
        doctorError.classList.add('d-none');
        doctorResult.classList.add('d-none');
        doctorResult.innerHTML = '';
        if (doctorCopyBtn) doctorCopyBtn.classList.add('d-none');
        if (doctorOpenFullBtn) doctorOpenFullBtn.classList.add('d-none');
        currentDoctorReport = '';
        doctorRerunBtn.disabled = true;

        try {
            const res = await fetch("{{ route('admin.skills.doctor') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();

            if (res.ok && data.success) {
                currentDoctorReport = data.report || '';
                doctorResult.innerHTML = marked.parse(currentDoctorReport);
                doctorResult.classList.remove('d-none');
                if (doctorCopyBtn) doctorCopyBtn.classList.remove('d-none');
                if (doctorOpenFullBtn) doctorOpenFullBtn.classList.remove('d-none');
                if (window.renderMath) {
                    window.renderMath(doctorResult);
                }
            } else {
                doctorError.textContent = data.message || 'Failed to complete analysis.';
                doctorError.classList.remove('d-none');
            }
        } catch (err) {
            doctorError.textContent = 'An error occurred while connecting to the Doctor protocol: ' + err.message;
            doctorError.classList.remove('d-none');
        } finally {
            doctorLoading.classList.add('d-none');
            doctorRerunBtn.disabled = false;
        }
    }

    if (doctorCopyBtn) {
        doctorCopyBtn.addEventListener('click', function () {
            if (!currentDoctorReport) return;
            navigator.clipboard.writeText(currentDoctorReport).then(() => {
                const originalHtml = doctorCopyBtn.innerHTML;
                doctorCopyBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Copied!';
                doctorCopyBtn.classList.replace('btn-outline-secondary', 'btn-success');
                setTimeout(() => {
                    doctorCopyBtn.innerHTML = originalHtml;
                    doctorCopyBtn.classList.replace('btn-success', 'btn-outline-secondary');
                }, 2000);
            }).catch(err => {
                alert('Failed to copy to clipboard: ' + err.message);
            });
        });
    }

    if (doctorOpenFullBtn) {
        doctorOpenFullBtn.addEventListener('click', function () {
            if (!currentDoctorReport) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('admin.skills.doctor.report') }}";
            form.target = '_blank';

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = document.querySelector('meta[name="csrf-token"]').content;
            form.appendChild(csrf);

            const input = document.createElement('textarea');
            input.name = 'report';
            input.value = currentDoctorReport;
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });
    }

    if (doctorModal) {
        doctorModal.addEventListener('show.bs.modal', function () {
            runDoctor();
        });
    }

    if (doctorRerunBtn) {
        doctorRerunBtn.addEventListener('click', function () {
            runDoctor();
        });
    }
});
</script>
@endsection
