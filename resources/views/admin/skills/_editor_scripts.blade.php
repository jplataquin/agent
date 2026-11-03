@push('styles')
<style>
.skills-editor-wrapper {
    position: relative;
    width: 100%;
}
@media (min-width: 992px) {
    .skills-editor-wrapper {
        min-height: calc(100vh - 165px);
        display: flex !important;
        flex-direction: row !important;
        align-items: stretch !important;
        width: 100%;
        overflow-x: hidden;
    }
    .skills-pane.skills-form-pane {
        flex: 0 0 auto !important;
        width: 70%;
        min-width: 320px;
        max-width: 85%;
        box-sizing: border-box;
    }
    .skills-pane.skills-mcp-pane {
        flex: 1 1 0 !important;
        min-width: 0 !important;
        width: auto !important;
        overflow: hidden;
        box-sizing: border-box;
    }
    .skills-resizer {
        width: 16px;
        cursor: col-resize;
        user-select: none;
        -webkit-user-select: none;
        display: flex !important;
        align-items: center;
        justify-content: center;
        margin: 0 2px;
        border-radius: 6px;
        transition: background-color 0.15s ease;
        flex-shrink: 0 !important;
        z-index: 20;
        touch-action: none;
    }
    .skills-resizer:hover, .skills-resizer.resizing {
        background-color: rgba(var(--bs-primary-rgb), 0.2);
    }
    .skills-resizer .resizer-bar {
        width: 4px;
        height: 54px;
        border-radius: 2px;
        background-color: var(--bs-border-color);
        transition: all 0.15s ease;
        pointer-events: none;
    }
    .skills-resizer:hover .resizer-bar, .skills-resizer.resizing .resizer-bar {
        background-color: var(--bs-primary);
        height: 80px;
        box-shadow: 0 0 8px rgba(var(--bs-primary-rgb), 0.6);
    }
}
@media (max-width: 991.98px) {
    .skills-resizer {
        display: none !important;
    }
    .skills-pane {
        width: 100% !important;
    }
}

/* Tab panes visibility fix */
#skillEditorTabContent > .tab-pane:not(.active) {
    display: none !important;
}
#skillEditorTabContent > .tab-pane.active {
    display: block !important;
    height: 100%;
}

/* Markdown preview styling */
#renderedMarkdownContent.markdown-body {
    font-size: 0.95rem;
    line-height: 1.6;
}
#renderedMarkdownContent.markdown-body h1,
#renderedMarkdownContent.markdown-body h2,
#renderedMarkdownContent.markdown-body h3,
#renderedMarkdownContent.markdown-body h4 {
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
}
#renderedMarkdownContent.markdown-body h1 {
    font-size: 1.6rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid var(--bs-border-color);
}
#renderedMarkdownContent.markdown-body h2 {
    font-size: 1.3rem;
    padding-bottom: 0.3rem;
    border-bottom: 1px solid var(--bs-border-color);
}
#renderedMarkdownContent.markdown-body h3 { font-size: 1.15rem; }
#renderedMarkdownContent.markdown-body p { margin-bottom: 0.75rem; }
#renderedMarkdownContent.markdown-body ul,
#renderedMarkdownContent.markdown-body ol {
    padding-left: 1.5rem;
    margin-bottom: 0.75rem;
}
#renderedMarkdownContent.markdown-body blockquote {
    border-left: 4px solid var(--bs-primary);
    padding: 0.5rem 1rem;
    margin: 0.75rem 0;
    color: var(--bs-secondary-color);
    background-color: var(--bs-tertiary-bg);
    border-radius: 0 6px 6px 0;
}
#renderedMarkdownContent.markdown-body table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
    font-size: 0.9rem;
}
#renderedMarkdownContent.markdown-body th,
#renderedMarkdownContent.markdown-body td {
    border: 1px solid var(--bs-border-color);
    padding: 6px 12px;
}
#renderedMarkdownContent.markdown-body th {
    background-color: var(--bs-tertiary-bg);
    font-weight: 600;
}
#renderedMarkdownContent.markdown-body :not(pre) > code {
    padding: 0.15em 0.35em;
    background-color: var(--bs-secondary-bg);
    border-radius: 4px;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.88em;
    color: var(--bs-primary-text-emphasis);
}
#renderedMarkdownContent.markdown-body pre {
    margin: 0.75rem 0;
    padding: 0.85rem 1rem;
    overflow-x: auto;
    border-radius: 6px;
    background: #1e1e2e;
    color: #f8f8f2;
}
#renderedMarkdownContent.markdown-body pre code {
    background: transparent;
    padding: 0;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- 1. Resizer Logic (Pointer & Mouse with 1:1 Direct Tracking) ---
    const resizer = document.getElementById('dragResizer');
    const leftPane = document.getElementById('leftPane');
    const wrapper = document.getElementById('skillsEditorWrapper');

    if (resizer && leftPane && wrapper) {
        let isResizing = false;

        function updateWidth(clientX) {
            const rect = wrapper.getBoundingClientRect();
            const containerWidth = rect.width;
            if (containerWidth <= 0) return;

            const pointerX = clientX - rect.left;
            const minW = Math.max(320, containerWidth * 0.25);
            const maxW = Math.min(containerWidth - 250, containerWidth * 0.85);

            const clampedWidth = Math.max(minW, Math.min(maxW, pointerX));
            const percentage = ((clampedWidth / containerWidth) * 100).toFixed(2) + '%';

            leftPane.style.width = percentage;
            leftPane.style.flexBasis = percentage;

            try {
                localStorage.setItem('skills_editor_left_width', percentage);
            } catch (err) {}
        }

        // Pointer Events (Hardware-accelerated tracking with pointer capture)
        resizer.addEventListener('pointerdown', function (e) {
            if (e.button !== 0 && e.pointerType === 'mouse') return;
            isResizing = true;
            resizer.classList.add('resizing');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            document.body.style.webkitUserSelect = 'none';

            try {
                resizer.setPointerCapture(e.pointerId);
            } catch (err) {}

            e.preventDefault();
        });

        resizer.addEventListener('pointermove', function (e) {
            if (!isResizing) return;
            e.preventDefault();
            updateWidth(e.clientX);
        });

        function stopPointerResize(e) {
            if (!isResizing) return;
            isResizing = false;
            resizer.classList.remove('resizing');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.body.style.webkitUserSelect = '';

            try {
                if (e && e.pointerId) {
                    resizer.releasePointerCapture(e.pointerId);
                }
            } catch (err) {}
        }

        resizer.addEventListener('pointerup', stopPointerResize);
        resizer.addEventListener('pointercancel', stopPointerResize);

        // Fallback Mouse Events for maximum compatibility
        resizer.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            isResizing = true;
            resizer.classList.add('resizing');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            document.body.style.webkitUserSelect = 'none';
            e.preventDefault();

            function onMouseMove(moveEvent) {
                if (!isResizing) return;
                moveEvent.preventDefault();
                updateWidth(moveEvent.clientX);
            }

            function onMouseUp() {
                if (!isResizing) return;
                isResizing = false;
                resizer.classList.remove('resizing');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                document.body.style.webkitUserSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        // Initialize default (70%) or restore saved width
        try {
            let saved = localStorage.getItem('skills_editor_left_width');
            if (saved && window.innerWidth >= 992) {
                const val = parseFloat(saved);
                if (!isNaN(val) && val < 65) {
                    saved = '70%';
                    localStorage.setItem('skills_editor_left_width', '70%');
                }
                leftPane.style.width = saved;
                leftPane.style.flexBasis = saved;
            } else if (window.innerWidth >= 992) {
                leftPane.style.width = '70%';
                leftPane.style.flexBasis = '70%';
            }
        } catch (e) {
            if (window.innerWidth >= 992) {
                leftPane.style.width = '70%';
                leftPane.style.flexBasis = '70%';
            }
        }
    }

    // --- 2. Instructions Textarea Cursor Insert Helper ---
    const instructionsTextarea = document.getElementById('skillInstructions');

    function insertAtCursor(textarea, textToInsert) {
        if (!textarea) return;
        textarea.focus();
        if (typeof textarea.selectionStart === 'number' && typeof textarea.selectionEnd === 'number') {
            const startPos = textarea.selectionStart;
            const endPos = textarea.selectionEnd;
            const originalVal = textarea.value;
            textarea.value = originalVal.substring(0, startPos) + textToInsert + originalVal.substring(endPos);
            textarea.selectionStart = startPos + textToInsert.length;
            textarea.selectionEnd = startPos + textToInsert.length;
        } else {
            textarea.value += textToInsert;
        }
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // --- 3. MCP Insert & Copy Tool Listeners ---
    document.querySelectorAll('.insert-tool-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const toolName = this.dataset.toolName;
            const toolDesc = this.dataset.toolDesc;
            if (!toolName) return;

            let insertText = '';
            if (instructionsTextarea) {
                const currentVal = instructionsTextarea.value.trim();
                const prefix = currentVal.length > 0 && !instructionsTextarea.value.endsWith('\n') ? '\n' : '';
                insertText = `${prefix}- Use \`${toolName}\` ${toolDesc ? 'to ' + toolDesc.toLowerCase() : ''}\n`;
                insertAtCursor(instructionsTextarea, insertText);
            }

            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="bi bi-check2 me-1"></i>Added';
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-success');
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.classList.remove('btn-success');
                this.classList.add('btn-outline-primary');
            }, 1200);
        });
    });

    document.querySelectorAll('.copy-tool-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const toolName = this.dataset.toolName;
            if (!toolName) return;

            navigator.clipboard.writeText(toolName).then(() => {
                const originalHTML = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check2 text-success"></i>';
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                }, 1200);
            }).catch(err => {
                console.error('Failed to copy tool name:', err);
            });
        });
    });

    // --- 4. MCP Explorer Tree Search / Filter ---
    const searchInput = document.getElementById('mcpTreeSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            const serverNodes = document.querySelectorAll('.mcp-server-node');

            serverNodes.forEach(serverNode => {
                const serverName = serverNode.dataset.serverName || '';
                const toolNodes = serverNode.querySelectorAll('.mcp-tool-node');
                let serverHasMatch = serverName.includes(q);
                let matchedToolsCount = 0;

                toolNodes.forEach(toolNode => {
                    const toolName = toolNode.dataset.toolName || '';
                    const toolDesc = toolNode.dataset.toolDesc || '';
                    const toolMatches = q === '' || serverHasMatch || toolName.includes(q) || toolDesc.includes(q);

                    if (toolMatches) {
                        toolNode.classList.remove('d-none');
                        matchedToolsCount++;
                    } else {
                        toolNode.classList.add('d-none');
                    }
                });

                if (q === '' || serverHasMatch || matchedToolsCount > 0) {
                    serverNode.classList.remove('d-none');
                    if (q !== '') {
                        const collapseEl = serverNode.querySelector('.accordion-collapse');
                        if (collapseEl && !collapseEl.classList.contains('show')) {
                            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl);
                            bsCollapse.show();
                        }
                    }
                } else {
                    serverNode.classList.add('d-none');
                }
            });
        });
    }

    // --- 5. Expand All / Collapse All ---
    const expandAllBtn = document.getElementById('mcpExpandAllBtn');
    const collapseAllBtn = document.getElementById('mcpCollapseAllBtn');

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function () {
            document.querySelectorAll('#mcpServersAccordion .accordion-collapse').forEach(collapseEl => {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl);
                bsCollapse.show();
            });
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function () {
            document.querySelectorAll('#mcpServersAccordion .accordion-collapse').forEach(collapseEl => {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl);
                bsCollapse.hide();
            });
        });
    }

    // --- 6. Rendered Markdown Preview Tab ---
    const previewTabBtn = document.getElementById('preview-tab');
    const previewContainer = document.getElementById('renderedMarkdownContent');
    const previewLoading = document.getElementById('previewLoading');

    if (previewTabBtn && previewContainer) {
        previewTabBtn.addEventListener('shown.bs.tab', function () {
            const form = document.getElementById('skillForm');
            if (!form) return;

            const name = form.querySelector('[name="name"]')?.value || '';
            const description = form.querySelector('[name="description"]')?.value || '';
            const instructions = form.querySelector('[name="instructions"]')?.value || '';

            // Show loading spinner
            previewLoading?.classList.remove('d-none');
            previewContainer.innerHTML = '';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            fetch('{{ route('admin.skills.resolve-preview') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({
                    name: name,
                    description: description,
                    instructions: instructions
                })
            })
            .then(res => {
                if (!res.ok) throw new Error('Failed to load preview');
                return res.json();
            })
            .then(data => {
                previewLoading?.classList.add('d-none');
                const rawMarkdown = data.markdown || '';
                
                if (window.marked) {
                    previewContainer.innerHTML = marked.parse(rawMarkdown);
                } else {
                    previewContainer.innerText = rawMarkdown;
                }

                // Apply syntax highlighting to code blocks if hljs is available
                if (window.hljs) {
                    previewContainer.querySelectorAll('pre code').forEach((block) => {
                        hljs.highlightElement(block);
                    });
                }
            })
            .catch(err => {
                previewLoading?.classList.add('d-none');
                previewContainer.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Failed to render preview: ${err.message}</div>`;
            });
        });
    }
});
</script>
@endpush
