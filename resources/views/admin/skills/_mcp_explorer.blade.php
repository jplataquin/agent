<div class="card border-0 shadow-sm rounded-3 h-100 d-flex flex-column mcp-explorer-card">
    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-diagram-3-fill text-primary"></i>
                <span>MCP Explorer</span>
                @php
                    $totalTools = 0;
                    if (isset($groupedTools)) {
                        foreach ($groupedTools as $tools) {
                            $totalTools += count($tools);
                        }
                    }
                @endphp
                <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill fs-7" id="mcpTotalToolsBadge">{{ $totalTools }} Tools</span>
            </h6>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="mcpExpandAllBtn" title="Expand All Servers">
                    <i class="bi bi-arrows-expand"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="mcpCollapseAllBtn" title="Collapse All Servers">
                    <i class="bi bi-arrows-collapse"></i>
                </button>
            </div>
        </div>

        <div class="position-relative">
            <input type="search" id="mcpTreeSearch" class="form-control form-control-sm pe-4 rounded-pill bg-body-tertiary" placeholder="Search servers, tools, parameters...">
            <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-3 text-body-secondary pe-none"></i>
        </div>
    </div>

    <div class="card-body p-3 pt-2 overflow-y-auto flex-grow-1" style="max-height: calc(100vh - 240px); min-height: 420px;" id="mcpTreeContainer">
        @include('admin.skills._mcp_tree', ['groupedTools' => $groupedTools, 'serversMap' => $serversMap ?? null])
    </div>
</div>

<style>
.btn-xs {
    padding: 0.15rem 0.45rem;
    font-size: 0.75rem;
    line-height: 1.2;
}
.cursor-pointer {
    cursor: pointer;
}
.last-border-0:last-child {
    border-bottom: 0 !important;
}
.mcp-params-details summary {
    user-select: none;
}
.mcp-params-details summary:hover {
    color: var(--bs-primary) !important;
}
#mcpTreeContainer::-webkit-scrollbar {
    width: 6px;
}
#mcpTreeContainer::-webkit-scrollbar-track {
    background: transparent;
}
#mcpTreeContainer::-webkit-scrollbar-thumb {
    background-color: var(--bs-border-color);
    border-radius: 4px;
}
#mcpTreeContainer::-webkit-scrollbar-thumb:hover {
    background-color: var(--bs-secondary);
}
</style>
