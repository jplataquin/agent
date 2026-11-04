@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Create Master Prompt</h4>
        <a href="{{ route('admin.master-prompts.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="skills-editor-wrapper d-flex flex-column flex-lg-row align-items-stretch" id="skillsEditorWrapper">
        <!-- Left Pane: Master Prompt Form / Text Editor -->
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
                            <form action="{{ route('admin.master-prompts.store') }}" method="POST" id="masterPromptForm" class="d-flex flex-column">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Prompt Title</label>
                                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" placeholder="e.g. Standard Corporate AI Assistant" value="{{ old('title') }}" required>
                                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description <small class="text-body-secondary fw-normal">(Optional)</small></label>
                                    <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror" placeholder="Context or internal purpose of this prompt">{{ old('description') }}</textarea>
                                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-semibold mb-0" for="masterPromptContent">System Prompt Content</label>
                                        <small class="text-body-secondary">Markdown supported &bull; Use <code>&#123;@skill-slug&#125;</code> to include a skill</small>
                                    </div>
                                    <textarea name="content" id="masterPromptContent" rows="12" class="form-control font-monospace @error('content') is-invalid @enderror" style="min-height: 280px; resize: vertical;" placeholder="You are Company AI Agent. Assist users with professional, helpful, and accurate answers..." required>{{ old('content') }}</textarea>
                                    @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveSwitch" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="isActiveSwitch">Set as active master prompt</label>
                                </div>

                                <div class="mt-auto">
                                    <button type="submit" class="btn btn-primary rounded-pill py-2 px-4 fw-semibold w-100">
                                        <i class="bi bi-plus-circle me-1"></i> Save Master Prompt
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
                                    <div class="mt-2 text-body-secondary small">Rendering Markdown...</div>
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
            @include('admin.skills._mcp_explorer', ['groupedTools' => $groupedTools, 'serversMap' => $serversMap ?? null])
        </div>
    </div>
</div>

@include('admin.skills._editor_scripts', [
    'availableSkills' => $availableSkills ?? [],
    'textareaId' => 'masterPromptContent',
    'formId' => 'masterPromptForm',
    'resolvePreviewUrl' => route('admin.master-prompts.resolve-preview'),
    'mcpTreeUrl' => route('admin.master-prompts.mcp-tree')
])
@endsection
