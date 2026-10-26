@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-body p-4 p-md-5 text-center bg-primary text-white">
                    <div class="display-4 fw-bold mb-3">
                        <i class="bi bi-robot"></i> Company AI Agent
                    </div>
                    <p class="lead mb-4">
                        Your intelligent workspace companion connected to internal systems via Model Context Protocol (MCP).
                    </p>
                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                        @auth
                            <a href="{{ route('chat.index') }}" class="btn btn-light btn-lg px-4 gap-3 fw-semibold">
                                Open Chat Agent
                            </a>
                            @if(Auth::user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-light btn-lg px-4">
                                    Admin Console
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn btn-light btn-lg px-4 gap-3 fw-semibold">
                                Log In
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg px-4">
                                    Register
                                </a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3 p-3 text-center">
                        <div class="fs-1 text-primary mb-2">⚡</div>
                        <h5 class="fw-bold">Connected Tools</h5>
                        <p class="text-muted small mb-0">Query internal databases, documentation, and APIs in real-time through HTTP MCP servers.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3 p-3 text-center">
                        <div class="fs-1 text-primary mb-2">📱</div>
                        <h5 class="fw-bold">Mobile First</h5>
                        <p class="text-muted small mb-0">Designed primarily for smartphones while providing a full responsive desktop interface.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-3 p-3 text-center">
                        <div class="fs-1 text-primary mb-2">📊</div>
                        <h5 class="fw-bold">Metrics & Auditing</h5>
                        <p class="text-muted small mb-0">Admins can monitor token usage, cost analytics, manage skills, and configure LLM providers.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
