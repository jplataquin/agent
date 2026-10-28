<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Company AI Agent') }}</title>

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0d6efd" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'AI Agent') }}">

    <!-- PWA Icons & Favicons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/icon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

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
    <link href="https://fonts.bunny.net/css?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Highlight.js for Syntax Highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark-dimmed.min.css">

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
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
        [data-bs-theme="dark"] .bg-light {
            background-color: #1e293b !important;
            color: #f1f5f9 !important;
        }
        [data-bs-theme="dark"] .bg-light.text-dark {
            color: #f1f5f9 !important;
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

        @media (max-width: 767.98px) {
            .navbar-collapse {
                background: inherit;
                padding: 12px 0 8px 0;
                border-top: 1px solid rgba(0, 0, 0, 0.08);
                margin-top: 8px;
            }
            [data-bs-theme="dark"] .navbar-collapse {
                border-top-color: rgba(255, 255, 255, 0.12);
            }
            .navbar-collapse .nav-item {
                padding: 3px 0;
            }
            [data-bs-theme="dark"] .navbar-toggler-icon {
                filter: invert(1) grayscale(100%) brightness(200%);
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
                    <!-- Mobile PWA Install Button -->
                    <button class="btn btn-sm btn-outline-primary rounded-circle pwa-install-btn d-none" type="button" title="Install App" aria-label="Install App">
                        <i class="bi bi-download"></i>
                    </button>
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
                                <li class="nav-item dropdown d-flex align-items-center">
                                    <a class="nav-link fw-semibold pe-1 {{ request()->is('admin*') ? 'active text-primary' : '' }}" href="{{ route('admin.dashboard') }}">
                                        <i class="bi bi-shield-lock me-1"></i> Admin Console
                                    </a>
                                    <a class="nav-link dropdown-toggle dropdown-toggle-split ps-1 text-muted" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Admin Menu Options">
                                        <span class="visually-hidden">Toggle Admin Console Menu</span>
                                    </a>
                                    <ul class="dropdown-menu shadow-sm border-0">
                                        <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.user-chats.index') }}"><i class="bi bi-chat-square-text me-2"></i>User Chat Sessions</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.users.index') }}"><i class="bi bi-people me-2"></i>Users</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.skills.index') }}"><i class="bi bi-tools me-2"></i>AI Skills</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.mcp-servers.index') }}"><i class="bi bi-hdd-network me-2"></i>HTTP MCP Servers</a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.built-in-tools.index') }}"><i class="bi bi-terminal me-2"></i>Built-in MCP Tools</a></li>
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
                        <!-- Desktop PWA Install Button -->
                        <li class="nav-item d-none" id="pwaInstallNavDesktop">
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1 pwa-install-btn d-flex align-items-center gap-1" type="button" title="Install App">
                                <i class="bi bi-download"></i>
                                <span class="small fw-semibold">Install App</span>
                            </button>
                        </li>

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
            @if(!request()->routeIs('chat.*'))
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
            @endif

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
                    if (window.mermaid) {
                        try {
                            mermaid.initialize({
                                startOnLoad: false,
                                theme: newTheme === 'dark' ? 'dark' : 'default',
                                securityLevel: 'loose'
                            });
                        } catch (e) {}
                    }
                });
            });
        });
    </script>

    <!-- Bootstrap 5.3 Bundle (Includes Popper & all Data-API plugins) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Fail-safe Mobile Navbar Burger Handler -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggler = document.querySelector('.navbar-toggler');
            const targetId = toggler ? toggler.getAttribute('data-bs-target') : '#navbarSupportedContent';
            const collapseEl = document.querySelector(targetId);

            if (toggler && collapseEl) {
                toggler.addEventListener('click', function(e) {
                    if (window.bootstrap && window.bootstrap.Collapse) {
                        try {
                            const inst = window.bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                            inst.toggle();
                            e.preventDefault();
                        } catch (err) {
                            collapseEl.classList.toggle('show');
                            const isExpanded = collapseEl.classList.contains('show');
                            toggler.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                            toggler.classList.toggle('collapsed', !isExpanded);
                        }
                    } else {
                        collapseEl.classList.toggle('show');
                        const isExpanded = collapseEl.classList.contains('show');
                        toggler.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                        toggler.classList.toggle('collapsed', !isExpanded);
                    }
                });
            }
        });
    </script>

    <!-- Markdown Parser & Highlight.js -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <!-- Mermaid.js for Diagrams & Flowcharts -->
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script>
        if (window.mermaid) {
            const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            mermaid.initialize({
                startOnLoad: false,
                theme: isDark ? 'dark' : 'default',
                securityLevel: 'loose'
            });
        }
    </script>

    <!-- PWA Install Banner / Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="pwaInstallToast" class="toast border shadow-lg bg-body" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
            <div class="toast-header border-bottom bg-body-secondary py-2">
                <img src="{{ asset('icons/icon-96x96.png') }}" class="rounded me-2" width="22" height="22" alt="Logo">
                <strong class="me-auto text-body">{{ config('app.name', 'Company AI Agent') }}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close" id="pwaDismissToastBtn"></button>
            </div>
            <div class="toast-body p-3">
                <p class="small text-secondary mb-3">Install this application for desktop launch, seamless offline capability, and instant access.</p>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="toast" id="pwaLaterBtn">Later</button>
                    <button type="button" class="btn btn-sm btn-primary pwa-install-btn px-3">
                        <i class="bi bi-download me-1"></i> Install
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PWA Offline Notice Toast -->
    <div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1090;">
        <div id="pwaOfflineToast" class="toast align-items-center text-bg-warning border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi bi-wifi-off fs-5"></i>
                    <span>You are currently offline. Cached views remain accessible.</span>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Service Worker Registration & PWA Install Controller -->
    <script>
        (function() {
            // 1. Service Worker Registration
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('{{ route('pwa.sw') }}', { scope: '/' })
                        .then(function(reg) {
                            reg.addEventListener('updatefound', function() {
                                const newWorker = reg.installing;
                                if (newWorker) {
                                    newWorker.addEventListener('statechange', function() {
                                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                            console.log('[PWA] New version installed and ready.');
                                        }
                                    });
                                }
                            });
                        })
                        .catch(function(err) {
                            console.warn('[PWA] ServiceWorker registration failed: ', err);
                        });
                });
            }

            // 2. Install Prompt Handling
            let deferredPrompt = null;
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

            const installButtons = document.querySelectorAll('.pwa-install-btn');
            const desktopNavContainer = document.getElementById('pwaInstallNavDesktop');
            const toastEl = document.getElementById('pwaInstallToast');

            function showInstallUI() {
                if (isStandalone) return;
                installButtons.forEach(btn => btn.classList.remove('d-none'));
                if (desktopNavContainer) {
                    desktopNavContainer.classList.remove('d-none');
                }

                const dismissedTime = localStorage.getItem('pwa_install_dismissed');
                const now = Date.now();
                // Avoid bugging users if dismissed within the last 5 days
                if (!dismissedTime || (now - parseInt(dismissedTime, 10)) > 5 * 24 * 60 * 60 * 1000) {
                    if (toastEl && window.bootstrap && window.bootstrap.Toast) {
                        try {
                            const toast = window.bootstrap.Toast.getOrCreateInstance(toastEl);
                            setTimeout(() => toast.show(), 2500);
                        } catch (e) {
                            console.error('[PWA] Error showing toast:', e);
                        }
                    }
                }
            }

            function hideInstallUI() {
                installButtons.forEach(btn => btn.classList.add('d-none'));
                if (desktopNavContainer) {
                    desktopNavContainer.classList.add('d-none');
                }
                if (toastEl && window.bootstrap && window.bootstrap.Toast) {
                    try {
                        const toast = window.bootstrap.Toast.getInstance(toastEl);
                        if (toast) toast.hide();
                    } catch (e) {}
                }
            }

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                showInstallUI();
            });

            document.addEventListener('click', function(e) {
                const target = e.target.closest('.pwa-install-btn');
                if (!target) return;

                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('[PWA] User accepted installation');
                        }
                        deferredPrompt = null;
                        hideInstallUI();
                    });
                } else {
                    alert('To install this app on your device:\n\n• On iPhone/iPad (Safari): Tap the Share button, then choose "Add to Home Screen".\n• On Android (Chrome): Tap the menu (3 dots) and select "Install app".\n• On Desktop (Chrome/Edge): Click the Install icon in the browser address bar.');
                }
            });

            // Dismiss actions
            const dismissBtns = [
                document.getElementById('pwaDismissToastBtn'),
                document.getElementById('pwaLaterBtn')
            ];
            dismissBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', () => {
                        localStorage.setItem('pwa_install_dismissed', Date.now().toString());
                    });
                }
            });

            window.addEventListener('appinstalled', () => {
                console.log('[PWA] App successfully installed');
                deferredPrompt = null;
                hideInstallUI();
            });

            // Online / Offline Indicators
            const offlineToastEl = document.getElementById('pwaOfflineToast');
            window.addEventListener('offline', () => {
                if (offlineToastEl && window.bootstrap && window.bootstrap.Toast) {
                    try {
                        const toast = window.bootstrap.Toast.getOrCreateInstance(offlineToastEl, { delay: 6000 });
                        toast.show();
                    } catch (e) {}
                }
            });

            window.addEventListener('online', () => {
                if (offlineToastEl && window.bootstrap && window.bootstrap.Toast) {
                    try {
                        const toast = window.bootstrap.Toast.getInstance(offlineToastEl);
                        if (toast) toast.hide();
                    } catch (e) {}
                }
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
