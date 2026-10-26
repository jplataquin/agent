@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-hdd-network text-primary me-2"></i>HTTP MCP Servers</h4>
            <p class="text-muted small mb-0">Model Context Protocol endpoints connecting the AI agent to your company databases, wiki, and APIs.</p>
        </div>
        <a href="{{ route('admin.mcp-servers.create') }}" class="btn btn-primary rounded-pill btn-sm px-3">
            <i class="bi bi-plus-lg me-1"></i> Register MCP Server
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-3">Server Name</th>
                        <th>Endpoint URL</th>
                        <th>Auth</th>
                        <th>Discovered Tools</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($servers as $server)
                        <tr>
                            <td class="ps-3 fw-bold">
                                {{ $server->name }}
                                @if($server->description)
                                    <div class="small text-muted fw-normal">{{ Str::limit($server->description, 50) }}</div>
                                @endif
                            </td>
                            <td><code>{{ $server->endpoint_url }}</code></td>
                            <td><span class="badge bg-secondary-subtle text-secondary">{{ strtoupper($server->auth_type) }}</span></td>
                            <td>
                                @php $toolsCount = count($server->tools_cache ?? []); @endphp
                                <span class="badge {{ $toolsCount > 0 ? 'bg-primary' : 'bg-warning text-dark' }}">
                                    {{ $toolsCount }} {{ Str::plural('tool', $toolsCount) }}
                                </span>
                            </td>
                            <td>
                                <form action="{{ route('admin.mcp-servers.toggle', $server) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm badge {{ $server->is_active ? 'bg-success' : 'bg-secondary' }} border-0">
                                        {{ $server->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-3">
                                <form action="{{ route('admin.mcp-servers.sync', $server) }}" method="POST" class="d-inline" title="Query tools/list">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info rounded-pill me-1">
                                        <i class="bi bi-arrow-repeat"></i> Sync
                                    </button>
                                </form>
                                <a href="{{ route('admin.mcp-servers.edit', $server) }}" class="btn btn-sm btn-outline-secondary rounded-pill me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.mcp-servers.destroy', $server) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete MCP server {{ $server->name }}?');">
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
                            <td colspan="6" class="text-center py-4 text-muted">No MCP servers registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
