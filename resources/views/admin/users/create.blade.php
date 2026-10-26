@extends('layouts.app')

@section('content')
<div class="container py-2" style="max-width: 600px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Create New User</h4>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Role</label>
                    <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                        <option value="regular" {{ old('role') === 'regular' ? 'selected' : '' }}>Regular Employee</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>System Administrator</option>
                    </select>
                    @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-semibold">
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
