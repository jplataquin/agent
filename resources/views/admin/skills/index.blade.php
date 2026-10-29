@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-tools text-primary me-2"></i>AI Skills</h4>
            <p class="text-muted small mb-0">Skills injected into the AI system prompt to guide responses for specific business domains.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-danger rounded-pill btn-sm px-3" id="runDoctorBtn" data-bs-toggle="modal" data-bs-target="#doctorModal">
                <i class="bi bi-heart-pulse me-1"></i> Doctor
            </button>
            <a href="{{ route('admin.skills.create') }}" class="btn btn-primary rounded-pill btn-sm px-3">
                <i class="bi bi-plus-lg me-1"></i> Add Skill
            </a>
        </div>
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

<!-- Doctor Modal -->
<div class="modal fade" id="doctorModal" tabindex="-1" aria-labelledby="doctorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="doctorModalLabel">
                    <i class="bi bi-heart-pulse text-danger me-2"></i>System Coherence Doctor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" id="doctorRerunBtn">
                    <i class="bi bi-arrow-repeat me-1"></i> Re-run Analysis
                </button>
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

    async function runDoctor() {
        doctorLoading.classList.remove('d-none');
        doctorError.classList.add('d-none');
        doctorResult.classList.add('d-none');
        doctorResult.innerHTML = '';
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
                doctorResult.innerHTML = marked.parse(data.report);
                doctorResult.classList.remove('d-none');
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
