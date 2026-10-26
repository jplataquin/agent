@extends('layouts.app')

@section('content')
<div class="container py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>User Management</h4>
            <p class="text-muted small mb-0">View, create, edit, and assign roles for Regular and Admin users.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary rounded-pill btn-sm px-3">
            <i class="bi bi-person-plus me-1"></i> Add User
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th class="ps-3">Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Registered</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="ps-3 fw-bold">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                <span class="badge {{ $user->isAdmin() ? 'bg-danger' : 'bg-secondary' }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $user->created_at->format('M d, Y') }}</td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary rounded-pill me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if($user->id !== auth()->id())
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete user {{ $user->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
