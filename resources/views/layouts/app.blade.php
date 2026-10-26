<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Company AI Agent') }}</title>

    <!-- Immediate Theme Application Script (Prevents flash) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('company_ai_theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    <!-- Fonts & Icons -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Highlight.js for Syntax Highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark-dimmed.min.css">

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        #app {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
        }
        .navbar-brand {
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        /* Dark Theme Polish */
        [data-bs-theme="dark"] body {
            background-color: #0f172a;
            color: #f1f5f9;
        }
        [data-bs-theme="dark"] .bg-white {
            background-color: #1e293b !important;
        }
        [data-bs-theme="dark"] .card {
            background-color: #1e293b;
            border-color: #334155 !important;
        }
        [data-bs-theme="dark"] .border-bottom, 
        [data-bs-theme="dark"] .border-top,
        [data-bs-theme="dark"] .border-right,
        [data-bs-theme="dark"] .border-end {
            border-color: #334155 !important;
        }
        [data-bs-theme="dark"] .table-light {
            background-color: #1e293b;
            color: #e2e8f0;
        }

        @media (max-width: 768px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white border-bottom sticky-top shadow-sm">
            <div class="container">
                <a class="navbar-brand text-primary d-flex align-items-center gap-2" href="{{ url('/') }}">
                    <i class="bi bi-robot fs-4"></i>
                    <span>{{ config('app.name', 'Company AI Agent') }}</span>
                </a>

                <div class="d-flex align-items-center gap-2 ms-auto d-md-none me-2">
                    <!-- Mobile Dark Mode Toggle -->
                    <button class="btn btn-sm btn-outline-secondary rounded-circle theme-toggle-btn" type="button" title="Toggle Dark/Light Mode">
                        <i class="bi bi-moon-stars theme-icon"></i>
                    </button>
                </div>

                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        @auth
                            <li class="nav-item">
                                <a class="nav-link fw-semibold {{ request()->routeIs('chat.*') ? 'active text-primary' : '' }}" href="{{ route('chat.index') }}">
                                    <i class="bi bi-chat-dots me-1"></i> AI Chat
                                </a>
                            </li>

                            @if(Auth::user()->isAdmin())
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle fw-semibold {{ request()->is('admin*') ? 'active text-primary' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-shield-lock me-1"></i> Admin Console
                                    </a>
                                    <ul class="dropdown-menu shadow-sm border-0">
                                        <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.user-chats.index') }}"><i class="bi bi-chat-square-text me-2"></i>User Chat Sessions</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.users.index') }}"><i class="bi bi-people me-2"></i>Users</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.skills.index') }}"><i class="bi bi-tools me-2"></i>AI Skills</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.mcp-servers.index') }}"><i class="bi bi-hdd-network me-2"></i>HTTP MCP Servers</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.master-prompts.index') }}"><i class="bi bi-file-text me-2"></i>Master Prompts</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.llm-providers.index') }}"><i class="bi bi-key me-2"></i>LLM Providers & Keys</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.metrics.index') }}"><i class="bi bi-graph-up-arrow me-2"></i>Token Metrics</a></li>
                                    </ul>
                                </li>
                            @endif
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto align-items-md-center gap-2">
                        <!-- Desktop Dark Mode Switcher -->
                        <li class="nav-item d-none d-md-block">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-1 theme-toggle-btn d-flex align-items-center gap-1" type="button" title="Toggle Dark/Light Mode">
                                <i class="bi bi-moon-stars theme-icon"></i>
                                <span class="small theme-label">Theme</span>
                            </button>
                        </li>

                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link fw-semibold" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link fw-semibold" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    <span class="fw-semibold">{{ Auth::user()->name }}</span>
                                    <span class="badge {{ Auth::user()->isAdmin() ? 'bg-danger' : 'bg-secondary' }} rounded-pill">
                                        {{ ucfirst(Auth::user()->role) }}
                                    </span>
                                </a>

                                <div class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        <i class="bi bi-box-arrow-right me-2"></i> {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-3">
            <div class="container">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>

            @yield('content')
        </main>
    </div>

    <!-- Theme Toggle Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtns = document.querySelectorAll('.theme-toggle-btn');
            const icons = document.querySelectorAll('.theme-icon');

            function updateIcons(theme) {
                icons.forEach(icon => {
                    if (theme === 'dark') {
                        icon.className = 'bi bi-sun-fill theme-icon text-warning';
                    } else {
                        icon.className = 'bi bi-moon-stars theme-icon';
                    }
                });
            }

            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            updateIcons(currentTheme);

            toggleBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const activeTheme = document.documentElement.getAttribute('data-bs-theme');
                    const newTheme = activeTheme === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('company_ai_theme', newTheme);
                    updateIcons(newTheme);
                });
            });
        });
    </script>

    <!-- Markdown Parser & Highlight.js -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    @stack('scripts')
</body>
</html>
