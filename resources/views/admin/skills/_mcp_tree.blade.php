@if(empty($groupedTools) || (is_countable($groupedTools) && count($groupedTools) === 0))
    <div class="text-center py-5 text-body-secondary">
        <i class="bi bi-hdd-network display-5 d-block mb-3 opacity-50"></i>
        <p class="mb-2 fw-semibold">No active MCP servers or tools discovered.</p>
        <small class="text-body-secondary d-block mb-3">Ensure your MCP servers are active and reachable to display available tools here.</small>
        <a href="{{ route('admin.mcp-servers.index') }}" class="btn btn-sm btn-outline-primary rounded-pill">
            <i class="bi bi-hdd-stack me-1"></i> Manage MCP Servers
        </a>
    </div>
@else
    <div class="accordion accordion-flush" id="mcpServersAccordion">
        @foreach($groupedTools as $serverName => $tools)
            @php
                $serverIndex = $loop->index;
                $firstTool = $tools[0] ?? null;
                $isInternal = !empty($firstTool['is_internal']) || str_contains(strtolower($serverName), 'internal');
                $server = isset($serversMap) ? ($serversMap[$serverName] ?? null) : null;
                $serverId = $server?->id ?? ($firstTool['server_id'] ?? null);
            @endphp
            <div class="accordion-item border rounded-3 mb-2 mcp-server-node bg-body" data-server-name="{{ strtolower($serverName) }}">
                <h2 class="accordion-header" id="headingServer{{ $serverIndex }}">
                    <button class="accordion-button rounded-3 py-2 px-3 fw-semibold text-body" type="button" data-bs-toggle="collapse" data-bs-target="#collapseServer{{ $serverIndex }}" aria-expanded="true" aria-controls="collapseServer{{ $serverIndex }}">
                        <div class="d-flex align-items-center justify-content-between w-100 me-2">
                            <div class="d-flex align-items-center gap-2 text-truncate">
                                @if($isInternal)
                                    <i class="bi bi-cpu-fill text-info fs-5 flex-shrink-0"></i>
                                @else
                                    <i class="bi bi-hdd-network-fill text-primary fs-5 flex-shrink-0"></i>
                                @endif
                                <span class="server-title text-truncate" style="max-width: 200px;" title="{{ $serverName }}">{{ $serverName }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                @if(!$isInternal && !empty($serverId))
                                    <button type="button" class="btn btn-outline-primary btn-xs rounded-pill sync-single-server-btn"
                                            data-server-id="{{ $serverId }}"
                                            data-server-name="{{ $serverName }}"
                                            data-sync-url="{{ route('admin.mcp-servers.sync', $serverId) }}"
                                            title="Sync tools from {{ $serverName }}"
                                            onclick="event.stopPropagation();">
                                        <i class="bi bi-arrow-repeat"></i>
                                        <span class="btn-text">Sync</span>
                                    </button>
                                @endif
                                <span class="badge {{ $isInternal ? 'bg-info-subtle text-info-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }} rounded-pill font-monospace server-tool-count">
                                    {{ count($tools) }} {{ Str::plural('tool', count($tools)) }}
                                </span>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="collapseServer{{ $serverIndex }}" class="accordion-collapse collapse show" aria-labelledby="headingServer{{ $serverIndex }}">
                    <div class="accordion-body p-2 pt-1">
                        @if(count($tools) === 0)
                            <div class="p-3 text-center text-body-secondary small bg-body-tertiary rounded-2">
                                <i class="bi bi-info-circle me-1"></i>No tools discovered yet.
                                @if(!$isInternal && !empty($serverId))
                                    Click <strong>Sync</strong> above to discover tools from this server.
                                @endif
                            </div>
                        @else
                            <div class="d-flex flex-column gap-2">
                                @foreach($tools as $toolIndex => $tool)
                                    @php
                                        $fn = $tool['function'] ?? [];
                                        $name = $fn['name'] ?? 'unnamed';
                                        $fullDesc = $fn['description'] ?? '';
                                        $cleanDesc = preg_replace('/\s*\(Provided by [^)]+\)\s*$/', '', $fullDesc);
                                        $parameters = $fn['parameters'] ?? [];
                                        $properties = $parameters['properties'] ?? [];
                                        $required = $parameters['required'] ?? [];
                                    @endphp
                                    <div class="border rounded-2 p-2 bg-body-tertiary mcp-tool-node" data-tool-name="{{ strtolower($name) }}" data-tool-desc="{{ strtolower($cleanDesc) }}">
                                        <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                            <div class="d-flex align-items-center gap-1 text-truncate">
                                                <i class="bi bi-wrench-adjustable text-secondary small"></i>
                                                <code class="fw-bold text-primary tool-name-display">{{ $name }}</code>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-outline-secondary btn-xs rounded-pill copy-tool-btn" data-tool-name="{{ $name }}" title="Copy tool name">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-primary btn-xs rounded-pill insert-tool-btn" data-tool-name="{{ $name }}" data-tool-desc="{{ $cleanDesc }}" title="Insert tool into instructions">
                                                    <i class="bi bi-plus-lg me-1"></i>Insert
                                                </button>
                                            </div>
                                        </div>

                                        @if(!empty($cleanDesc))
                                            <p class="small text-body-secondary mb-1 lh-sm tool-description">{{ $cleanDesc }}</p>
                                        @endif

                                        @if(!empty($properties) && is_array($properties))
                                            <details class="mcp-params-details mt-1">
                                                <summary class="small text-body-secondary cursor-pointer fw-semibold py-1">
                                                    <span>Arguments ({{ count($properties) }})</span>
                                                </summary>
                                                <div class="bg-body rounded p-2 mt-1 border small">
                                                    @foreach($properties as $propName => $propDef)
                                                        @php
                                                            $isReq = in_array($propName, $required, true);
                                                            $propType = $propDef['type'] ?? 'any';
                                                            $propDesc = $propDef['description'] ?? '';
                                                        @endphp
                                                        <div class="py-1 border-bottom border-light-subtle last-border-0">
                                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                                <code class="text-body-emphasis fw-bold">{{ $propName }}</code>
                                                                <span class="badge bg-secondary-subtle text-secondary-emphasis font-monospace px-1 py-0">{{ $propType }}</span>
                                                                @if($isReq)
                                                                    <span class="badge bg-danger-subtle text-danger-emphasis px-1 py-0">required</span>
                                                                @endif
                                                            </div>
                                                            @if(!empty($propDesc))
                                                                <div class="text-body-secondary mt-0.5" style="font-size: 0.82rem;">{{ $propDesc }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
