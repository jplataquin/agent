@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Add AI Skill</h4>
        <a href="{{ route('admin.skills.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="skills-editor-wrapper d-flex flex-column flex-lg-row align-items-stretch" id="skillsEditorWrapper">
        <!-- Left Pane: Skill Form / Text Editor -->
        <div class="skills-pane skills-form-pane" id="leftPane" style="width: 70%; min-width: 320px;">
            <div class="card border-0 shadow-sm rounded-3 h-100 d-flex flex-column">
                <div class="card-header bg-transparent border-bottom-0 pb-0 pt-3 px-4">
                    <ul class="nav nav-tabs card-header-tabs" id="skillEditorTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold" id="editor-tab" data-bs-toggle="tab" data-bs-target="#editor-tab-pane" type="button" role="tab" aria-controls="editor-tab-pane" aria-selected="true">
                                <i class="bi bi-pencil-square me-1"></i> Text Editor
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview-tab-pane" type="button" role="tab" aria-controls="preview-tab-pane" aria-selected="false">
                                <i class="bi bi-eye me-1"></i> Rendered Preview
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4 d-flex flex-column flex-grow-1">
                    <div class="tab-content flex-grow-1" id="skillEditorTabContent">
                        <!-- Tab 1: Editor Form -->
                        <div class="tab-pane fade show active" id="editor-tab-pane" role="tabpanel" aria-labelledby="editor-tab" tabindex="0">
                            <form action="{{ route('admin.skills.store') }}" method="POST" id="skillForm" class="d-flex flex-column">
                                @csrf
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Skill Name</label>
                                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. IT & Software Support" value="{{ old('name') }}" required>
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Slug <small class="text-muted fw-normal">(Optional)</small></label>
                                        <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror" placeholder="it-software-support" value="{{ old('slug') }}">
                                        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror" placeholder="What domain or capability does this skill address?">{{ old('description') }}</textarea>
                                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-semibold mb-0" for="skillInstructions">Instructions for AI Agent</label>
                                        <small class="text-body-secondary">Markdown supported &bull; Use <code>&#123;@skill-slug&#125;</code> to include another skill</small>
                                    </div>
                                    <textarea name="instructions" id="skillInstructions" rows="12" class="form-control font-monospace @error('instructions') is-invalid @enderror" style="min-height: 280px; resize: vertical;" placeholder="Provide guidelines, business logic, constraints, links, and tone the AI should apply when handling topics covered by this skill." required>{{ old('instructions') }}</textarea>
                                    @error('instructions') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Enable this skill immediately</label>
                                </div>

                                <div class="mt-auto">
                                    <button type="submit" class="btn btn-primary rounded-pill py-2 px-4 fw-semibold w-100">
                                        <i class="bi bi-plus-circle me-1"></i> Save Skill
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tab 2: Rendered Markdown -->
                        <div class="tab-pane fade" id="preview-tab-pane" role="tabpanel" aria-labelledby="preview-tab" tabindex="0">
                            <div class="p-3 border rounded-3 bg-body-tertiary overflow-y-auto" style="min-height: 480px; max-height: calc(100vh - 250px);">
                                <div id="previewLoading" class="text-center py-5 d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading preview...</span>
                                    </div>
                                    <div class="mt-2 text-muted small">Rendering Markdown...</div>
                                </div>
                                <div id="renderedMarkdownContent" class="markdown-body">
                                    <!-- Rendered HTML will be injected here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resizer Handle -->
        <div class="skills-resizer d-none d-lg-flex" id="dragResizer" title="Drag horizontally to resize panes">
            <div class="resizer-bar"></div>
        </div>

        <!-- Right Pane: MCP Explorer Collapsable Tree -->
        <div class="skills-pane skills-mcp-pane flex-grow-1 mt-3 mt-lg-0" id="rightPane" style="min-width: 0;">
            @include('admin.skills._mcp_explorer', ['groupedTools' => $groupedTools])
        </div>
    </div>
</div>

@include('admin.skills._editor_scripts')
@endsection
