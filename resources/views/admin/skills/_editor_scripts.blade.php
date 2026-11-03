@push('styles')
<style>
.skills-editor-wrapper {
    position: relative;
    width: 100%;
}
@media (min-width: 992px) {
    .skills-editor-wrapper {
        min-height: calc(100vh - 170px);
    }
    .skills-pane {
        height: 100%;
    }
    .skills-resizer {
        width: 14px;
        cursor: col-resize;
        user-select: none;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 4px;
        border-radius: 6px;
        transition: background-color 0.15s ease;
        flex-shrink: 0;
    }
    .skills-resizer:hover, .skills-resizer.resizing {
        background-color: rgba(var(--bs-primary-rgb), 0.15);
    }
    .skills-resizer .resizer-bar {
        width: 4px;
        height: 48px;
        border-radius: 2px;
        background-color: var(--bs-border-color);
        transition: all 0.15s ease;
    }
    .skills-resizer:hover .resizer-bar, .skills-resizer.resizing .resizer-bar {
        background-color: var(--bs-primary);
        height: 72px;
        box-shadow: 0 0 6px rgba(var(--bs-primary-rgb), 0.5);
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
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- 1. Resizer Logic ---
    const resizer = document.getElementById('dragResizer');
    const leftPane = document.getElementById('leftPane');
    const wrapper = document.getElementById('skillsEditorWrapper');

    if (resizer && leftPane && wrapper) {
        let isResizing = false;
        let startX = 0;
        let startWidth = 0;

        resizer.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            isResizing = true;
            startX = e.clientX;
            startWidth = leftPane.getBoundingClientRect().width;
            resizer.classList.add('resizing');
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            function onMouseMove(e) {
                if (!isResizing) return;
                const dx = e.clientX - startX;
                const newWidth = startWidth + dx;
                const containerWidth = wrapper.getBoundingClientRect().width;

                const minW = Math.max(320, containerWidth * 0.25);
                const maxW = Math.min(containerWidth - 300, containerWidth * 0.80);

                if (newWidth >= minW && newWidth <= maxW) {
                    const percentage = (newWidth / containerWidth) * 100;
                    leftPane.style.width = percentage + '%';
                    try {
                        localStorage.setItem('skills_editor_left_width', percentage + '%');
                    } catch (e) {}
                }
            }

            function onMouseUp() {
                if (!isResizing) return;
                isResizing = false;
                resizer.classList.remove('resizing');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        // Restore saved preference if on desktop
        try {
            const savedWidth = localStorage.getItem('skills_editor_left_width');
            if (savedWidth && window.innerWidth >= 992) {
                leftPane.style.width = savedWidth;
            }
        } catch (e) {}
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
});
</script>
@endpush
