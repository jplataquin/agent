@extends('layouts.app')

@push('styles')
<style>
    /* Chat Layout */
    .chat-container {
        height: calc(100vh - 120px);
        min-height: 540px;
        display: flex !important;
        flex-direction: column !important;
        position: relative;
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .chat-header {
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        position: relative;
        z-index: 10;
        flex-shrink: 0;
    }
    .chat-body {
        flex: 1 1 0%;
        min-height: 0;
        display: flex !important;
        flex-direction: row !important;
        position: relative;
        overflow: hidden;
    }
    .chat-sidebar {
        width: 290px;
        min-width: 290px;
        max-width: 290px;
        height: 100%;
        background: #ffffff;
        border-right: 1px solid #e2e8f0;
        display: flex !important;
        flex-direction: column !important;
        padding: 14px;
        overflow: hidden;
        flex-shrink: 0;
        position: relative;
        z-index: 2;
    }
    .chat-sidebar.transition-ready {
        transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), min-width 0.25s cubic-bezier(0.4, 0, 0.2, 1), max-width 0.25s cubic-bezier(0.4, 0, 0.2, 1), padding 0.25s ease, opacity 0.2s ease;
    }
    .chat-sidebar.collapsed {
        width: 0 !important;
        min-width: 0 !important;
        max-width: 0 !important;
        padding: 0 !important;
        border-right: none !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
    .chat-main {
        flex: 1 1 0%;
        min-width: 0;
        height: 100%;
        display: flex !important;
        flex-direction: column !important;
        background: #f8fafc;
        overflow: hidden;
        position: relative;
        z-index: 1;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* Message Bubbles */
    .message-row {
        display: flex;
        flex-direction: column;
        width: 100%;
        position: relative;
    }
    .message-row.user-row {
        align-items: flex-end;
    }
    .message-row.assistant-row {
        align-items: flex-start;
    }
    .message-bubble {
        max-width: 85%;
        padding: 14px 18px;
        border-radius: 18px;
        position: relative;
        word-break: break-word;
        font-size: 0.95rem;
        line-height: 1.6;
    }
    .message-user {
        background-color: #0d6efd;
        color: #ffffff;
        border-bottom-right-radius: 4px;
    }
    .message-assistant {
        background-color: #ffffff;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .message-tool {
        display: none !important;
    }

    /* Message Action Bar */
    .message-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 4px;
        font-size: 0.8rem;
    }
    .action-btn {
        background: transparent;
        border: none;
        color: #94a3b8;
        padding: 4px 8px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .action-btn:hover {
        background: rgba(0,0,0,0.06);
        color: #334155;
    }
    .action-btn.active-like {
        color: #10b981;
    }
    .action-btn.active-dislike {
        color: #ef4444;
    }
    .action-btn.speaking {
        color: #6366f1;
        font-weight: 600;
    }

    /* User Prompt Edit Button */
    .user-edit-btn {
        opacity: 0.6;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .user-edit-btn:hover {
        opacity: 1;
    }

    /* Voice / Microphone Recording State */
    .mic-listening {
        background-color: #ef4444 !important;
        color: #ffffff !important;
        border-color: #ef4444 !important;
        animation: pulse-mic 1.5s infinite;
    }
    @keyframes pulse-mic {
        0% { transform: scale(0.96); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1.06); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.96); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    /* Rich Markdown Styles within Assistant Bubbles */
    .message-assistant h1, .message-assistant h2, .message-assistant h3, .message-assistant h4 {
        margin-top: 1rem;
        margin-bottom: 0.5rem;
        font-weight: 700;
        line-height: 1.3;
    }
    .message-assistant h1 { font-size: 1.35rem; }
    .message-assistant h2 { font-size: 1.2rem; }
    .message-assistant h3 { font-size: 1.05rem; }
    .message-assistant p {
        margin-bottom: 0.75rem;
    }
    .message-assistant p:last-child {
        margin-bottom: 0;
    }
    .message-assistant ul, .message-assistant ol {
        padding-left: 1.5rem;
        margin-bottom: 0.75rem;
    }
    .message-assistant li {
        margin-bottom: 0.25rem;
    }
    .message-assistant table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
        font-size: 0.88rem;
    }
    .message-assistant th, .message-assistant td {
        border: 1px solid #cbd5e1;
        padding: 6px 10px;
    }
    .message-assistant th {
        background-color: rgba(0,0,0,0.05);
        font-weight: 600;
    }

    /* Code Blocks & Copy Button */
    .code-block-wrapper {
        position: relative;
        margin: 0.75rem 0;
        border-radius: 8px;
        overflow: hidden;
        background: #1e1e2e;
    }
    .code-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #181825;
        color: #a6adc8;
        padding: 4px 12px;
        font-size: 0.75rem;
        font-family: monospace;
    }
    .copy-code-btn {
        background: transparent;
        border: none;
        color: #a6adc8;
        cursor: pointer;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 2px 6px;
        border-radius: 4px;
    }
    .copy-code-btn:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
    }
    .message-assistant pre {
        margin: 0;
        padding: 12px;
        overflow-x: auto;
    }
    .message-assistant code {
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.85rem;
    }
    .message-assistant p code, .message-assistant li code {
        background: rgba(0,0,0,0.06);
        padding: 2px 5px;
        border-radius: 4px;
        font-size: 0.85em;
        color: #d63384;
    }

    /* Input area */
    .chat-input-area {
        background: #ffffff;
        border-top: 1px solid #e2e8f0;
        padding: 14px 18px;
        flex-shrink: 0;
    }

    /* Session List Item */
    .session-item {
        border-radius: 10px;
        padding: 8px 12px;
        color: #334155;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
        transition: all 0.15s ease-in-out;
    }
    .session-item:hover, .session-item.active {
        background-color: #eff6ff;
        color: #1d4ed8;
        font-weight: 600;
    }
    .session-item.pinned {
        border-left: 3px solid #3b82f6;
    }
    .session-header-tag {
        font-size: 0.70rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        padding: 8px 8px 4px 8px;
    }

    /* Dark Mode Theme Adjustments */
    [data-bs-theme="dark"] .chat-header {
        background: #1e293b;
        border-color: #334155;
    }
    [data-bs-theme="dark"] .chat-sidebar {
        background: #1e293b;
        border-color: #334155;
    }
    [data-bs-theme="dark"] #conversationSearchInput {
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #f1f5f9;
    }
    [data-bs-theme="dark"] .chat-sidebar .input-group-text {
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #94a3b8;
    }
    [data-bs-theme="dark"] .chat-main {
        background: #0f172a;
    }
    [data-bs-theme="dark"] .chat-input-area {
        background: #1e293b;
        border-color: #334155;
    }
    [data-bs-theme="dark"] .message-assistant {
        background-color: #1e293b;
        color: #f1f5f9;
        border-color: #334155;
    }
    [data-bs-theme="dark"] .message-assistant p code,
    [data-bs-theme="dark"] .message-assistant li code {
        background: #334155;
        color: #f43f5e;
    }
    [data-bs-theme="dark"] .message-tool {
        background-color: #1e293b;
        color: #cbd5e1;
        border-left-color: #818cf8;
    }
    [data-bs-theme="dark"] .session-item {
        color: #cbd5e1;
    }
    [data-bs-theme="dark"] .session-item:hover,
    [data-bs-theme="dark"] .session-item.active {
        background-color: #334155;
        color: #60a5fa;
    }
    [data-bs-theme="dark"] .action-btn {
        color: #64748b;
    }
    [data-bs-theme="dark"] .action-btn:hover {
        background: rgba(255,255,255,0.08);
        color: #e2e8f0;
    }

    @media (max-width: 767.98px) {
        /* Lock mobile page height and explicitly override desktop min-height: 100vh */
        html, body {
            height: 100dvh !important;
            min-height: 100dvh !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        #app {
            height: 100dvh !important;
            min-height: 100dvh !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }
        /* Ensure navbar doesn't shrink and remains pinned at the top */
        .navbar {
            flex-shrink: 0 !important;
        }
        main {
            flex: 1 1 0% !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
        }
        main > .container-fluid {
            flex: 1 1 0% !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
        }
        .chat-container {
            flex: 1 1 0%;
            height: 100% !important;
            min-height: 0 !important;
            border-radius: 0 !important;
            border-left: none !important;
            border-right: none !important;
            border-bottom: none !important;
        }
        .chat-sidebar {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 285px;
            max-width: 85%;
            z-index: 1040;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.18);
            transform: translateX(-100%);
            opacity: 0;
            pointer-events: none;
            border-right: 1px solid #e2e8f0;
        }
        .chat-sidebar.transition-ready {
            transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease;
        }
        .chat-sidebar.mobile-open {
            transform: translateX(0) !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }
        .chat-sidebar.collapsed {
            transform: translateX(-100%) !important;
            width: 285px !important;
            min-width: 285px !important;
            max-width: 85% !important;
            padding: 14px !important;
            opacity: 0 !important;
            pointer-events: none !important;
            border-right: 1px solid #e2e8f0 !important;
        }
        .chat-sidebar-backdrop {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1030;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .chat-sidebar-backdrop.show {
            opacity: 1;
            pointer-events: auto;
        }
        .message-bubble {
            max-width: 92%;
        }
    }
    @media (min-width: 768px) {
        .chat-sidebar-backdrop {
            display: none !important;
        }
    }

    /* Chat Toast Notifications (Absolute, High z-index, Non-nudging) */
    .chat-toast-container {
        position: absolute !important;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999 !important;
        width: calc(100% - 32px);
        max-width: 480px;
        pointer-events: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .chat-toast,
    .toast-notification {
        position: absolute !important;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999 !important;
        width: calc(100% - 32px);
        max-width: 480px;
        pointer-events: auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        border: 1px solid #e2e8f0;
        font-size: 0.9rem;
        line-height: 1.4;
        transition: opacity 0.25s ease, transform 0.25s ease;
        animation: toastSlideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .chat-toast-container .chat-toast,
    .chat-toast-container .toast-notification {
        position: relative !important;
        top: auto;
        left: auto;
        transform: none;
        width: 100%;
    }

    @keyframes toastSlideDown {
        from {
            opacity: 0;
            transform: translateY(-12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .chat-toast.toast-hiding {
        opacity: 0 !important;
        transform: translateY(-8px) !important;
    }

    .chat-toast.toast-success {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #166534;
    }
    .chat-toast.toast-danger,
    .chat-toast.toast-error {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }
    .chat-toast.toast-warning {
        background: #fffbeb;
        border-color: #fde68a;
        color: #92400e;
    }
    .chat-toast.toast-info {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1e40af;
    }

    [data-bs-theme="dark"] .chat-toast,
    [data-bs-theme="dark"] .toast-notification {
        background: #1e293b;
        border-color: #334155;
        color: #f1f5f9;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
    }
    [data-bs-theme="dark"] .chat-toast.toast-success {
        background: #064e3b;
        border-color: #059669;
        color: #ecfdf5;
    }
    [data-bs-theme="dark"] .chat-toast.toast-danger,
    [data-bs-theme="dark"] .chat-toast.toast-error {
        background: #7f1d1d;
        border-color: #dc2626;
        color: #fef2f2;
    }
    [data-bs-theme="dark"] .chat-toast.toast-warning {
        background: #78350f;
        border-color: #d97706;
        color: #fffbeb;
    }
    [data-bs-theme="dark"] .chat-toast.toast-info {
        background: #1e3a8a;
        border-color: #2563eb;
        color: #eff6ff;
    }

    .toast-content {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-grow: 1;
        min-width: 0;
    }
    .toast-message {
        word-break: break-word;
        font-weight: 500;
    }
    .toast-close-btn {
        background: transparent;
        border: none;
        padding: 4px;
        margin-left: 10px;
        color: currentColor;
        opacity: 0.65;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: opacity 0.15s ease, background-color 0.15s ease;
        line-height: 1;
        flex-shrink: 0;
    }
    .toast-close-btn:hover {
        opacity: 1;
        background-color: rgba(0, 0, 0, 0.08);
    }
    [data-bs-theme="dark"] .toast-close-btn:hover {
        background-color: rgba(255, 255, 255, 0.15);
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 px-md-4">
    <div class="chat-container shadow-sm position-relative">
        
        <!-- Toast Notification Container (Absolute, High z-index, Non-nudging) -->
        <div id="chatToastContainer" class="chat-toast-container toast-container position-absolute" aria-live="polite" aria-atomic="true">
            @if(session('success'))
                <div class="chat-toast toast-notification toast-success shadow" role="alert" data-autohide="true">
                    <div class="toast-content">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <span class="toast-message">{{ session('success') }}</span>
                    </div>
                    <button type="button" class="toast-close-btn btn-close" aria-label="Close"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="chat-toast toast-notification toast-danger shadow" role="alert" data-autohide="true">
                    <div class="toast-content">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                        <span class="toast-message">{{ session('error') }}</span>
                    </div>
                    <button type="button" class="toast-close-btn btn-close" aria-label="Close"></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="chat-toast toast-notification toast-warning shadow" role="alert" data-autohide="true">
                    <div class="toast-content">
                        <i class="bi bi-exclamation-circle-fill text-warning fs-5"></i>
                        <span class="toast-message">{{ session('warning') }}</span>
                    </div>
                    <button type="button" class="toast-close-btn btn-close" aria-label="Close"></button>
                </div>
            @endif
            @if(session('info'))
                <div class="chat-toast toast-notification toast-info shadow" role="alert" data-autohide="true">
                    <div class="toast-content">
                        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                        <span class="toast-message">{{ session('info') }}</span>
                    </div>
                    <button type="button" class="toast-close-btn btn-close" aria-label="Close"></button>
                </div>
            @endif
            @if(session('status'))
                <div class="chat-toast toast-notification toast-info shadow" role="alert" data-autohide="true">
                    <div class="toast-content">
                        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                        <span class="toast-message">{{ session('status') }}</span>
                    </div>
                    <button type="button" class="toast-close-btn btn-close" aria-label="Close"></button>
                </div>
            @endif
        </div>

        <!-- Chat Top Header Bar -->
        <div class="chat-header d-flex align-items-center justify-content-between p-2 p-md-3">
            <div class="d-flex align-items-center gap-2">
                <!-- Single Unified Sidebar Toggle Button -->
                <button class="btn btn-outline-secondary btn-sm rounded-pill d-inline-flex align-items-center gap-1" id="toggleSidebarBtn" type="button" title="Show Sidebar" aria-label="Show Sidebar" aria-expanded="false">
                    <i class="bi bi-layout-sidebar" id="toggleSidebarIcon"></i>
                    <span class="small d-none d-lg-inline" id="toggleSidebarText">Sidebar</span>
                </button>

                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="fw-bold mb-0 text-truncate" style="max-width: 280px;" id="activeHeaderTitle">
                            {{ $activeSession ? $activeSession->title : 'Company AI Agent' }}
                        </h6>
                        @if(isset($activeProvider) && $activeProvider)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace" style="font-size: 0.72rem;" title="Active Provider: {{ $activeProvider->name }}">
                                <i class="bi bi-cpu me-1"></i> {{ $activeProvider->name }} ({{ $activeProvider->model }})
                            </span>
                        @else
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.72rem;">
                                <i class="bi bi-exclamation-triangle me-1"></i> No Active LLM
                            </span>
                        @endif
                    </div>
                    @php
                        $activeMcpToolsCount = count(app(\App\Services\McpService::class)->getAllActiveTools());
                    @endphp
                    <small class="text-muted d-block" style="font-size: 0.75rem;">
                        @if($activeMcpToolsCount > 0)
                            <i class="bi bi-shield-check text-success me-1"></i> {{ $activeMcpToolsCount }} {{ Str::plural('MCP Tool', $activeMcpToolsCount) }} &amp; Voice Connected
                        @else
                            <i class="bi bi-shield-exclamation text-warning me-1"></i> 0 MCP Tools Active (Voice Ready)
                        @endif
                    </small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.llm-providers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill d-none d-sm-inline-flex align-items-center gap-1" title="Manage Providers & Logs">
                        <i class="bi bi-gear"></i> <span class="small">Providers</span>
                    </a>
                @endif
                <form action="{{ route('chat.store') }}" method="POST" class="d-md-none">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-2" title="New Conversation">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Chat Body (Sidebar + Messages) -->
        <div class="chat-body">
            <!-- Sidebar Backdrop for Mobile overlay -->
            <div class="chat-sidebar-backdrop" id="chatSidebarBackdrop"></div>

            <!-- Unified Conversations Sidebar -->
            <aside class="chat-sidebar collapsed" id="chatSidebar" aria-label="Conversations Sidebar">
                <!-- Sidebar Header (Dedicated close button removed) -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold small text-secondary text-uppercase tracking-wider">
                            <i class="bi bi-chat-square-text me-1 text-primary"></i> Conversations
                        </span>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill font-monospace" style="font-size: 0.72rem;">
                            {{ $sessions->count() }}
                        </span>
                    </div>
                </div>

            <!-- New Chat Action -->
            <form action="{{ route('chat.store') }}" method="POST" class="mb-2">
                @csrf
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 shadow-sm d-flex align-items-center justify-content-center gap-2 fw-semibold">
                    <i class="bi bi-plus-lg"></i>
                    <span>New Conversation</span>
                </button>
            </form>

            <!-- Search Chats Input -->
            <div class="mb-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-body-secondary border-end-0 text-muted ps-2 pe-1">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="conversationSearchInput" class="form-control form-control-sm bg-body-secondary border-start-0" placeholder="Filter conversations..." autocomplete="off">
                </div>
            </div>

            <!-- Sessions List -->
            <div class="overflow-auto flex-grow-1 pe-1" id="sessionListDesktop">
                @php
                    $pinnedSessions = $sessions->where('is_pinned', true);
                    $regularSessions = $sessions->where('is_pinned', false);
                @endphp

                @if($pinnedSessions->isNotEmpty())
                    <div class="session-header-tag d-flex align-items-center gap-1" id="pinnedHeaderTag">
                        <i class="bi bi-pin-angle-fill text-primary"></i> Pinned Chats
                    </div>
                    @foreach($pinnedSessions as $s)
                        <div class="session-wrapper position-relative mb-1 session-pinned" id="session-row-{{ $s->id }}" data-session-title="{{ strtolower($s->title) }}">
                            <div class="session-item pinned {{ $activeSession && $activeSession->id === $s->id ? 'active' : '' }}">
                                <a href="{{ route('chat.show', $s) }}" class="text-truncate text-decoration-none flex-grow-1 me-2 text-reset" id="session-title-link-{{ $s->id }}">
                                    <i class="bi bi-pin-angle-fill text-primary me-1" title="Pinned"></i>
                                    <span class="session-title-text" id="title-text-{{ $s->id }}">{{ $s->title }}</span>
                                </a>
                                <div class="dropdown">
                                    <button class="btn btn-sm p-1 text-muted border-0 session-options-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-1" style="font-size: 0.85rem;">
                                        <li>
                                            <button class="dropdown-item py-1" onclick="togglePinSession({{ $s->id }})">
                                                <i class="bi bi-pin-angle me-2"></i> Unpin
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item py-1" onclick="startInlineRename({{ $s->id }}, '{{ addslashes($s->title) }}')">
                                                <i class="bi bi-pencil me-2"></i> Rename
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button type="button" class="dropdown-item py-1 text-danger" onclick="deleteSession(event, {{ $s->id }})">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="rename-container d-none p-1" id="rename-box-{{ $s->id }}">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="rename-input-{{ $s->id }}" value="{{ $s->title }}">
                                    <button class="btn btn-primary" onclick="submitInlineRename({{ $s->id }})"><i class="bi bi-check"></i></button>
                                    <button class="btn btn-outline-secondary" onclick="cancelInlineRename({{ $s->id }})"><i class="bi bi-x"></i></button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if($regularSessions->isNotEmpty())
                    @if($pinnedSessions->isNotEmpty())
                        <div class="session-header-tag pt-2" id="recentHeaderTag">
                            Recent Chats
                        </div>
                    @endif
                    @foreach($regularSessions as $s)
                        <div class="session-wrapper position-relative mb-1 session-regular" id="session-row-{{ $s->id }}" data-session-title="{{ strtolower($s->title) }}">
                            <div class="session-item {{ $activeSession && $activeSession->id === $s->id ? 'active' : '' }}">
                                <a href="{{ route('chat.show', $s) }}" class="text-truncate text-decoration-none flex-grow-1 me-2 text-reset" id="session-title-link-{{ $s->id }}">
                                    <i class="bi bi-chat-left-text me-1 text-muted"></i>
                                    <span class="session-title-text" id="title-text-{{ $s->id }}">{{ $s->title }}</span>
                                </a>
                                <div class="dropdown">
                                    <button class="btn btn-sm p-1 text-muted border-0 session-options-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-1" style="font-size: 0.85rem;">
                                        <li>
                                            <button class="dropdown-item py-1" onclick="togglePinSession({{ $s->id }})">
                                                <i class="bi bi-pin-angle-fill me-2"></i> Pin to top
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item py-1" onclick="startInlineRename({{ $s->id }}, '{{ addslashes($s->title) }}')">
                                                <i class="bi bi-pencil me-2"></i> Rename
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button type="button" class="dropdown-item py-1 text-danger" onclick="deleteSession(event, {{ $s->id }})">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="rename-container d-none p-1" id="rename-box-{{ $s->id }}">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="rename-input-{{ $s->id }}" value="{{ $s->title }}">
                                    <button class="btn btn-primary" onclick="submitInlineRename({{ $s->id }})"><i class="bi bi-check"></i></button>
                                    <button class="btn btn-outline-secondary" onclick="cancelInlineRename({{ $s->id }})"><i class="bi bi-x"></i></button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                <div class="text-center text-muted small py-5 {{ $sessions->isEmpty() ? '' : 'd-none' }}" id="emptySessionsNotice">
                    <i class="bi bi-chat-dots fs-3 d-block mb-2 text-secondary"></i>
                    No conversations yet.<br>Click <strong>New Conversation</strong> to begin.
                </div>
            </div>
        </aside>

        <!-- Chat Main Area -->
        <div class="chat-main">
            <!-- Messages List -->
            <div class="chat-messages" id="chatMessages">
                @if(! $activeSession)
                    <div class="text-center my-auto py-5 text-muted">
                        <div class="display-1 text-primary mb-3"><i class="bi bi-robot"></i></div>
                        <h3 class="fw-bold">Company AI Agent</h3>
                        <p class="small">Query enterprise information systems, analyze data, and communicate via voice.</p>
                        <form action="{{ route('chat.store') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm">Start a Conversation</button>
                        </form>
                    </div>
                @else
                    @forelse($activeSession->messages as $message)
                        @if($message->role === 'user')
                            <div class="message-row user-row" id="msg-row-{{ $message->id }}">
                                <div class="message-bubble message-user d-flex align-items-start gap-2">
                                    <div class="user-content-text flex-grow-1" id="user-content-{{ $message->id }}">{{ $message->content }}</div>
                                    <button class="btn btn-sm p-0 text-white user-edit-btn" onclick="startEditUserPrompt({{ $message->id }})" title="Edit prompt">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                                <!-- Inline Edit Box (Hidden by default) -->
                                <div class="user-edit-box d-none w-75 mt-2" id="user-edit-box-{{ $message->id }}">
                                    <textarea class="form-control mb-2 rounded-3" id="edit-textarea-{{ $message->id }}" rows="2">{{ $message->content }}</textarea>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="cancelEditUserPrompt({{ $message->id }})">Cancel</button>
                                        <button class="btn btn-sm btn-primary" onclick="submitEditUserPrompt({{ $message->id }})">Update &amp; Send</button>
                                    </div>
                                </div>
                            </div>

                        @elseif($message->role === 'assistant')
                            @if(!empty($message->content))
                            <div class="message-row assistant-row" id="msg-row-{{ $message->id }}">
                                <div class="message-bubble message-assistant markdown-body" id="assistant-content-{{ $message->id }}" data-raw-content="{{ e($message->content) }}">
                                    {!! $message->content !!}
                                </div>
                                <!-- Assistant Message Actions -->
                                <div class="message-actions px-2">
                                    <button class="action-btn" onclick="copyMessageText('assistant-content-{{ $message->id }}')" title="Copy message">
                                        <i class="bi bi-clipboard"></i> <span>Copy</span>
                                    </button>
                                    <!-- Text-to-Speech Button -->
                                    <button class="action-btn tts-btn" onclick="toggleSpeakMessage('assistant-content-{{ $message->id }}', this)" title="Listen to response">
                                        <i class="bi bi-volume-up"></i> <span>Listen</span>
                                    </button>
                                    <button class="action-btn {{ $message->feedback === 'like' ? 'active-like' : '' }}" onclick="submitFeedback({{ $message->id }}, 'like', this)" title="Good response">
                                        <i class="bi bi-hand-thumbs-up"></i>
                                    </button>
                                    <button class="action-btn {{ $message->feedback === 'dislike' ? 'active-dislike' : '' }}" onclick="submitFeedback({{ $message->id }}, 'dislike', this)" title="Bad response">
                                        <i class="bi bi-hand-thumbs-down"></i>
                                    </button>
                                    <button class="action-btn" onclick="regenerateResponse()" title="Regenerate response">
                                        <i class="bi bi-arrow-clockwise"></i> <span>Regenerate</span>
                                    </button>
                                </div>
                            </div>
                            @endif
                        @endif
                    @empty
                        <div class="text-center text-muted my-auto py-5" id="emptyState">
                            <div class="fs-1 text-primary mb-2"><i class="bi bi-chat-quote"></i></div>
                            <h5 class="fw-bold">How can I help you today?</h5>
                            <p class="small text-muted">Type your request, speak into the microphone, or query internal tools and web data.</p>
                        </div>
                    @endforelse
                @endif

                <div id="typingIndicator" class="message-row assistant-row d-none">
                    <div class="message-bubble message-assistant d-flex align-items-center gap-2">
                        <div class="spinner-grow spinner-grow-sm text-primary" role="status"></div>
                        <span class="small text-muted" id="typingText">Thinking &amp; analyzing systems...</span>
                    </div>
                </div>
            </div>

            <!-- Chat Input Area -->
            @if($activeSession)
                <div class="chat-input-area" id="chatInputArea">
                    <form id="chatForm" class="d-flex gap-2 align-items-end">
                        @csrf

                        <textarea 
                            id="messageInput" 
                            name="message" 
                            rows="1" 
                            class="form-control rounded-4 shadow-none flex-grow-1" 
                            placeholder="Ask a question or speak into microphone..." 
                            required 
                            style="resize: none;"></textarea>

                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <!-- Speech-to-Text Microphone Button -->
                            <button type="button" id="micBtn" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px; flex-shrink: 0;" title="Click to speak (Speech to Text)">
                                <i class="bi bi-mic-fill fs-5" id="micIcon"></i>
                            </button>

                            <!-- Submit Button -->
                            <button type="submit" id="sendBtn" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px; flex-shrink: 0;" title="Send message">
                                <i class="bi bi-arrow-up-circle-fill fs-5"></i>
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Unified Sidebar Toggle & Filtering (Works on Mobile and Desktop)
    (function() {
        const chatSidebar = document.getElementById('chatSidebar');
        const chatSidebarBackdrop = document.getElementById('chatSidebarBackdrop');
        const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');
        const toggleSidebarIcon = document.getElementById('toggleSidebarIcon');
        const toggleSidebarText = document.getElementById('toggleSidebarText');
        const searchInput = document.getElementById('conversationSearchInput');

        function isMobile() {
            return window.innerWidth < 768;
        }

        function isSidebarOpen() {
            if (!chatSidebar) return false;
            if (isMobile()) {
                return chatSidebar.classList.contains('mobile-open');
            }
            return !chatSidebar.classList.contains('collapsed');
        }

        function updateSidebarUI(isOpen) {
            if (!toggleSidebarBtn) return;
            if (isOpen) {
                toggleSidebarBtn.setAttribute('title', 'Hide Sidebar');
                toggleSidebarBtn.setAttribute('aria-expanded', 'true');
                if (toggleSidebarIcon) toggleSidebarIcon.className = 'bi bi-layout-sidebar-inset';
                if (toggleSidebarText) toggleSidebarText.textContent = 'Sidebar';
            } else {
                toggleSidebarBtn.setAttribute('title', 'Show Sidebar');
                toggleSidebarBtn.setAttribute('aria-expanded', 'false');
                if (toggleSidebarIcon) toggleSidebarIcon.className = 'bi bi-layout-sidebar';
                if (toggleSidebarText) toggleSidebarText.textContent = 'Sidebar';
            }
        }

        function setSidebarState(open) {
            if (!chatSidebar) return;
            const mobile = isMobile();

            if (open) {
                if (mobile) {
                    chatSidebar.classList.add('mobile-open');
                    chatSidebar.classList.remove('collapsed');
                    if (chatSidebarBackdrop) chatSidebarBackdrop.classList.add('show');
                } else {
                    chatSidebar.classList.remove('collapsed');
                    localStorage.setItem('chat_sidebar_collapsed', '0');
                }
                updateSidebarUI(true);
            } else {
                if (mobile) {
                    chatSidebar.classList.remove('mobile-open');
                    chatSidebar.classList.add('collapsed');
                    if (chatSidebarBackdrop) chatSidebarBackdrop.classList.remove('show');
                } else {
                    chatSidebar.classList.add('collapsed');
                    localStorage.setItem('chat_sidebar_collapsed', '1');
                }
                updateSidebarUI(false);
            }
        }

        if (toggleSidebarBtn && chatSidebar) {
            toggleSidebarBtn.addEventListener('click', function () {
                setSidebarState(!isSidebarOpen());
            });
        }

        if (chatSidebarBackdrop) {
            chatSidebarBackdrop.addEventListener('click', function () {
                setSidebarState(false);
            });
        }

        // Initialize state on page load (Default to CLOSED)
        if (chatSidebar) {
            if (isMobile()) {
                setSidebarState(false);
            } else {
                // Closed by default; only open if user explicitly chose to keep it open ('0')
                const isSavedOpen = localStorage.getItem('chat_sidebar_collapsed') === '0';
                setSidebarState(isSavedOpen);
            }

            // Enable transition animations after initial layout is applied so toggling animates smoothly without refresh flicker
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    chatSidebar.classList.add('transition-ready');
                });
            });
        }

        // Handle responsive resize dynamically
        window.addEventListener('resize', function () {
            if (!chatSidebar) return;
            if (isMobile()) {
                if (!chatSidebar.classList.contains('mobile-open')) {
                    setSidebarState(false);
                }
            } else {
                if (chatSidebarBackdrop) chatSidebarBackdrop.classList.remove('show');
                chatSidebar.classList.remove('mobile-open');
                const isSavedOpen = localStorage.getItem('chat_sidebar_collapsed') === '0';
                setSidebarState(isSavedOpen);
            }
        });

        // Conversations Search Filtering
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('#sessionListDesktop .session-wrapper');
                const tags = document.querySelectorAll('#sessionListDesktop .session-header-tag');

                rows.forEach(row => {
                    const title = row.getAttribute('data-session-title') || '';
                    if (!query || title.includes(query)) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });

                tags.forEach(tag => {
                    if (query) {
                        tag.classList.add('d-none');
                    } else {
                        tag.classList.remove('d-none');
                    }
                });
            });
        }
    })();

    // Toast Notification System (Absolute, High z-index, Auto-dismiss or Close button)
    window.dismissToast = function(toastEl) {
        if (!toastEl || toastEl._isDismissed) return;
        toastEl._isDismissed = true;
        toastEl.classList.add('toast-hiding');
        setTimeout(function() {
            if (toastEl.parentNode) {
                toastEl.parentNode.removeChild(toastEl);
            }
        }, 250);
    };

    window.showToast = function(message, type = 'info', duration = 4000) {
        let container = document.getElementById('chatToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'chatToastContainer';
            container.className = 'chat-toast-container toast-container position-absolute';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            const targetContainer = document.querySelector('.chat-container') || document.body;
            targetContainer.appendChild(container);
        }

        const toast = document.createElement('div');
        const normalizedType = type === 'error' ? 'danger' : type;
        toast.className = `chat-toast toast-notification toast-${normalizedType} shadow`;
        toast.setAttribute('role', 'alert');

        let iconClass = 'bi-info-circle-fill text-primary';
        if (normalizedType === 'success') iconClass = 'bi-check-circle-fill text-success';
        else if (normalizedType === 'danger') iconClass = 'bi-exclamation-triangle-fill text-danger';
        else if (normalizedType === 'warning') iconClass = 'bi-exclamation-circle-fill text-warning';

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        toast.innerHTML = `
            <div class="toast-content">
                <i class="bi ${iconClass} fs-5"></i>
                <span class="toast-message">${escapeHtml(message)}</span>
            </div>
            <button type="button" class="toast-close-btn btn-close" aria-label="Close"></button>
        `;

        const closeBtn = toast.querySelector('.toast-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                window.dismissToast(toast);
            });
        }

        container.appendChild(toast);

        if (duration > 0) {
            setTimeout(function() {
                window.dismissToast(toast);
            }, duration);
        }

        return toast;
    };

    // Auto-dismiss pre-rendered server session toasts & bind close handlers
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#chatToastContainer .chat-toast').forEach(function(toast) {
            const closeBtn = toast.querySelector('.toast-close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.dismissToast(toast);
                });
            }
            if (toast.getAttribute('data-autohide') === 'true') {
                setTimeout(function() {
                    window.dismissToast(toast);
                }, 4000);
            }
        });
    });

    // Fallback: route any legacy window.alert calls in /chat to showToast
    window.alert = function(msg) {
        window.showToast(msg, 'info');
    };

    // Active session ID tracker
    window.activeChatSessionId = {{ $activeSession ? $activeSession->id : 'null' }};

    window.updateSessionListVisibility = function () {
        const pinnedRows = document.querySelectorAll('#sessionListDesktop .session-pinned');
        const regularRows = document.querySelectorAll('#sessionListDesktop .session-regular');
        const pinnedTag = document.getElementById('pinnedHeaderTag');
        const recentTag = document.getElementById('recentHeaderTag');
        const emptyNotice = document.getElementById('emptySessionsNotice');

        if (pinnedTag) {
            pinnedTag.classList.toggle('d-none', pinnedRows.length === 0);
        }
        if (recentTag) {
            recentTag.classList.toggle('d-none', regularRows.length === 0);
        }
        if (emptyNotice) {
            emptyNotice.classList.toggle('d-none', pinnedRows.length > 0 || regularRows.length > 0);
        }
    };

    window.resetToEmptyChatState = function () {
        window.history.pushState({}, '', '{{ route("chat.index") }}');

        const activeHeaderTitle = document.getElementById('activeHeaderTitle');
        if (activeHeaderTitle) {
            activeHeaderTitle.textContent = 'Company AI Agent';
        }

        const inputArea = document.getElementById('chatInputArea');
        if (inputArea) {
            inputArea.classList.add('d-none');
        }

        const messagesContainer = document.getElementById('chatMessages');
        if (messagesContainer) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            messagesContainer.innerHTML = `
                <div class="text-center my-auto py-5 text-muted" id="chatEmptyStateContainer">
                    <div class="display-1 text-primary mb-3"><i class="bi bi-robot"></i></div>
                    <h3 class="fw-bold">Company AI Agent</h3>
                    <p class="small">Query enterprise information systems, analyze data, and communicate via voice.</p>
                    <form action="{{ route('chat.store') }}" method="POST">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm">Start a Conversation</button>
                    </form>
                </div>
            `;
        }

        window.activeChatSessionId = null;
    };

    window.deleteSession = function (event, sid) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (!confirm('Delete this conversation?')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(`/chat/${sid}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Failed to delete conversation');
            }
            return res.json();
        })
        .then(data => {
            const row = document.getElementById(`session-row-${sid}`);
            if (row) {
                row.style.transition = 'all 0.25s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-10px)';
                setTimeout(() => {
                    row.remove();
                    window.updateSessionListVisibility();
                }, 250);
            }

            if (window.activeChatSessionId === sid) {
                window.resetToEmptyChatState();
            }

            if (typeof window.showToast === 'function') {
                window.showToast('Conversation deleted.', 'success');
            }
        })
        .catch(err => {
            console.error(err);
            if (typeof window.showToast === 'function') {
                window.showToast('Could not delete the conversation. Please try again.', 'danger');
            } else {
                alert('Could not delete the conversation. Please try again.');
            }
        });
    };

    window.togglePinSession = function(sid) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        fetch(`/chat/${sid}/pin`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        }).then(res => res.json()).then(data => {
            location.reload();
        }).catch(err => {
            if (typeof window.showToast === 'function') {
                window.showToast('Failed to update pin state.', 'danger');
            }
        });
    };

    window.startInlineRename = function(sid, currentTitle) {
        document.getElementById(`session-title-link-${sid}`).classList.add('d-none');
        document.getElementById(`rename-box-${sid}`).classList.remove('d-none');
        const input = document.getElementById(`rename-input-${sid}`);
        input.focus();
        input.select();
    };

    window.cancelInlineRename = function(sid) {
        document.getElementById(`rename-box-${sid}`).classList.add('d-none');
        document.getElementById(`session-title-link-${sid}`).classList.remove('d-none');
    };

    window.submitInlineRename = function(sid) {
        const newTitle = document.getElementById(`rename-input-${sid}`).value.trim();
        if (!newTitle) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(`/chat/${sid}/rename`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ title: newTitle })
        }).then(res => res.json()).then(data => {
            const titleElem = document.getElementById(`title-text-${sid}`);
            if (titleElem) titleElem.textContent = data.title;
            if (window.activeChatSessionId && sid === window.activeChatSessionId) {
                const headerTitle = document.getElementById('activeHeaderTitle');
                if (headerTitle) headerTitle.textContent = data.title;
            }
            cancelInlineRename(sid);
            if (typeof window.showToast === 'function') {
                window.showToast('Conversation renamed.', 'success');
            }
        }).catch(err => {
            if (typeof window.showToast === 'function') {
                window.showToast('Failed to rename conversation.', 'danger');
            }
        });
    };
</script>

@if($activeSession)
<script>
    const sessionId = {{ $activeSession->id }};
    const messageUrl = "{{ route('chat.message', $activeSession) }}";
    const streamUrl = "{{ route('chat.stream', $activeSession) }}";
    const renameUrl = "{{ route('chat.rename', $activeSession) }}";
    const pinUrl = "{{ route('chat.pin', $activeSession) }}";
    const regenerateUrl = "{{ route('chat.regenerate', $activeSession) }}";
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // 1. Markdown Rendering & Syntax Highlighting with "Copy Code" button
    function renderMarkdown(element) {
        if (!element) return;
        const raw = element.getAttribute('data-raw-content') || element.innerHTML;
        
        // Parse with marked
        const parsed = marked.parse(raw);
        element.innerHTML = parsed;

        // Apply syntax highlighting and copy code buttons to all <pre><code> blocks
        element.querySelectorAll('pre code').forEach((block) => {
            hljs.highlightElement(block);

            const pre = block.parentElement;
            if (!pre.parentElement.classList.contains('code-block-wrapper')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'code-block-wrapper';

                const lang = (block.className.match(/language-(\w+)/) || [, 'code'])[1];

                const header = document.createElement('div');
                header.className = 'code-header';
                header.innerHTML = `<span>${lang}</span><button class="copy-code-btn" type="button"><i class="bi bi-clipboard"></i> Copy code</button>`;

                const copyBtn = header.querySelector('.copy-code-btn');
                copyBtn.addEventListener('click', function() {
                    navigator.clipboard.writeText(block.innerText).then(() => {
                        copyBtn.innerHTML = '<i class="bi bi-check2 text-success"></i> Copied!';
                        setTimeout(() => {
                            copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy code';
                        }, 2000);
                    });
                });

                pre.parentNode.insertBefore(wrapper, pre);
                wrapper.appendChild(header);
                wrapper.appendChild(pre);
            }
        });
    }

    // Render all initial assistant messages
    document.querySelectorAll('.message-assistant.markdown-body').forEach(el => renderMarkdown(el));

    // 2. Message Actions: Copy Message Text
    window.copyMessageText = function(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const text = el.innerText;
        navigator.clipboard.writeText(text).then(() => {
            window.showToast('Response copied to clipboard!', 'success');
        }).catch(() => {
            window.showToast('Failed to copy text to clipboard.', 'danger');
        });
    };

    // 3. Text-to-Speech (TTS)
    let currentSpeechUtterance = null;
    let currentSpeechBtn = null;

    window.toggleSpeakMessage = function(contentId, btn) {
        if (!('speechSynthesis' in window)) {
            window.showToast('Text-to-Speech is not supported by your browser.', 'warning');
            return;
        }

        // If currently speaking this message, stop it
        if (window.speechSynthesis.speaking && currentSpeechBtn === btn) {
            window.speechSynthesis.cancel();
            btn.classList.remove('speaking');
            btn.innerHTML = '<i class="bi bi-volume-up"></i> <span>Listen</span>';
            currentSpeechBtn = null;
            return;
        }

        // Stop any previous speech
        window.speechSynthesis.cancel();
        if (currentSpeechBtn) {
            currentSpeechBtn.classList.remove('speaking');
            currentSpeechBtn.innerHTML = '<i class="bi bi-volume-up"></i> <span>Listen</span>';
        }

        const el = document.getElementById(contentId);
        if (!el) return;

        // Clone element to clean text for natural speech (remove code blocks and markdown artifacts)
        const clone = el.cloneNode(true);
        clone.querySelectorAll('pre, code, table').forEach(node => node.remove());
        let textToRead = clone.innerText.trim();

        if (!textToRead) {
            textToRead = el.innerText.trim();
        }

        const utterance = new SpeechSynthesisUtterance(textToRead);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;

        btn.classList.add('speaking');
        btn.innerHTML = '<i class="bi bi-stop-circle-fill text-danger"></i> <span>Stop</span>';
        currentSpeechBtn = btn;
        currentSpeechUtterance = utterance;

        utterance.onend = function() {
            btn.classList.remove('speaking');
            btn.innerHTML = '<i class="bi bi-volume-up"></i> <span>Listen</span>';
            currentSpeechBtn = null;
        };

        utterance.onerror = function() {
            btn.classList.remove('speaking');
            btn.innerHTML = '<i class="bi bi-volume-up"></i> <span>Listen</span>';
            currentSpeechBtn = null;
            window.showToast('An error occurred during audio playback.', 'danger');
        };

        window.speechSynthesis.speak(utterance);
    };

    // 4. Message Actions: Feedback (thumbs up / thumbs down)
    window.submitFeedback = function(messageId, type, btn) {
        const url = `/chat/${sessionId}/messages/${messageId}/feedback`;
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ feedback: type })
        }).then(res => res.json()).then(data => {
            const parent = btn.parentElement;
            parent.querySelectorAll('.action-btn').forEach(b => {
                b.classList.remove('active-like', 'active-dislike');
            });
            if (data.feedback === 'like') {
                btn.classList.add('active-like');
                window.showToast('Feedback submitted: Good response.', 'success');
            }
            if (data.feedback === 'dislike') {
                btn.classList.add('active-dislike');
                window.showToast('Feedback submitted: Bad response.', 'info');
            }
        }).catch(err => {
            window.showToast('Failed to submit feedback.', 'danger');
        });
    };

    // 5. Message Actions: Regenerate Response
    window.regenerateResponse = function() {
        if (!confirm('Regenerate response for this turn?')) return;
        const typingIndicator = document.getElementById('typingIndicator');
        typingIndicator.classList.remove('d-none');

        fetch(regenerateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        }).then(res => res.json()).then(data => {
            location.reload();
        }).catch(err => {
            typingIndicator.classList.add('d-none');
            window.showToast('Failed to regenerate response.', 'danger');
        });
    };

    // 6. Editable User Prompts
    window.startEditUserPrompt = function(messageId) {
        document.getElementById(`user-content-${messageId}`).parentElement.classList.add('d-none');
        document.getElementById(`user-edit-box-${messageId}`).classList.remove('d-none');
    };

    window.cancelEditUserPrompt = function(messageId) {
        document.getElementById(`user-content-${messageId}`).parentElement.classList.remove('d-none');
        document.getElementById(`user-edit-box-${messageId}`).classList.add('d-none');
    };

    window.submitEditUserPrompt = function(messageId) {
        const newText = document.getElementById(`edit-textarea-${messageId}`).value.trim();
        if (!newText) return;

        const editUrl = `/chat/${sessionId}/messages/${messageId}`;
        const typingIndicator = document.getElementById('typingIndicator');
        typingIndicator.classList.remove('d-none');

        fetch(editUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ content: newText })
        }).then(res => res.json()).then(data => {
            location.reload();
        }).catch(err => {
            typingIndicator.classList.add('d-none');
            window.showToast('Error updating message.', 'danger');
        });
    };

    // 8. Auto-scroll, Speech-to-Text & Chat Form Handling
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('chatForm');
        const input = document.getElementById('messageInput');
        const messagesContainer = document.getElementById('chatMessages');
        const typingIndicator = document.getElementById('typingIndicator');
        const emptyState = document.getElementById('emptyState');
        const sendBtn = document.getElementById('sendBtn');
        const micBtn = document.getElementById('micBtn');
        const micIcon = document.getElementById('micIcon');

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        scrollToBottom();

        input.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = (Math.min(this.scrollHeight, 120)) + 'px';
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });

        // Speech-to-Text (STT) implementation
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        let recognition = null;
        let isListening = false;

        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = true;
            recognition.lang = 'en-US';

            recognition.onstart = function() {
                isListening = true;
                micBtn.classList.add('mic-listening');
                micIcon.className = 'bi bi-mic-mute-fill fs-5 text-white';
                input.placeholder = 'Listening... Speak now';
            };

            recognition.onresult = function(event) {
                let transcript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    transcript += event.results[i][0].transcript;
                }
                input.value = transcript;
                input.style.height = 'auto';
                input.style.height = (Math.min(input.scrollHeight, 120)) + 'px';
            };

            recognition.onerror = function(event) {
                console.warn('Speech recognition error:', event.error);
                stopListening();
            };

            recognition.onend = function() {
                stopListening();
            };

            function stopListening() {
                isListening = false;
                micBtn.classList.remove('mic-listening');
                micIcon.className = 'bi bi-mic-fill fs-5';
                input.placeholder = 'Ask a question or speak into microphone...';
            }

            micBtn.addEventListener('click', function() {
                if (isListening) {
                    recognition.stop();
                } else {
                    try {
                        recognition.start();
                    } catch (err) {
                        console.error('Speech recognition start failed:', err);
                        window.showToast('Failed to start speech recognition: ' + err.message, 'danger');
                    }
                }
            });
        } else {
            micBtn.addEventListener('click', function() {
                window.showToast('Speech-to-Text is not supported in this browser. Please use Google Chrome, Microsoft Edge, or Safari.', 'warning');
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const text = input.value.trim();
            if (!text) return;

            if (isListening && recognition) {
                recognition.stop();
            }

            input.value = '';
            input.style.height = 'auto';
            input.disabled = true;
            sendBtn.disabled = true;

            if (emptyState) emptyState.classList.add('d-none');

            // Append User Message to UI
            const userRow = document.createElement('div');
            userRow.className = 'message-row user-row';
            userRow.innerHTML = `<div class="message-bubble message-user">${text}</div>`;
            messagesContainer.insertBefore(userRow, typingIndicator);
            scrollToBottom();

            // Show typing indicator
            typingIndicator.classList.remove('d-none');
            scrollToBottom();

            // Send via reliable POST request
            const newMsgId = 'msg-' + Date.now();

            fetch(messageUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message: text })
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(errData => {
                        throw new Error(errData.message || errData.content || `Server returned HTTP ${res.status}`);
                    }).catch(() => {
                        throw new Error(`Server returned HTTP ${res.status}`);
                    });
                }
                return res.json();
            })
            .then(data => {
                typingIndicator.classList.add('d-none');
                input.disabled = false;
                sendBtn.disabled = false;
                input.focus();

                const aiReply = data.ai_response || {};
                const replyText = aiReply.content || aiReply.error || 'Response completed.';

                const assistantRow = document.createElement('div');
                assistantRow.className = 'message-row assistant-row';
                const assistantBubble = document.createElement('div');
                assistantBubble.className = 'message-bubble message-assistant markdown-body';
                assistantBubble.id = newMsgId;
                assistantBubble.setAttribute('data-raw-content', replyText);
                assistantRow.appendChild(assistantBubble);

                // Add action buttons
                const actions = document.createElement('div');
                actions.className = 'message-actions px-2';
                actions.innerHTML = `
                    <button class="action-btn" onclick="copyMessageText('${newMsgId}')" title="Copy message">
                        <i class="bi bi-clipboard"></i> <span>Copy</span>
                    </button>
                    <button class="action-btn tts-btn" onclick="toggleSpeakMessage('${newMsgId}', this)" title="Listen to response">
                        <i class="bi bi-volume-up"></i> <span>Listen</span>
                    </button>
                    <button class="action-btn" onclick="regenerateResponse()" title="Regenerate response">
                        <i class="bi bi-arrow-clockwise"></i> <span>Regenerate</span>
                    </button>
                `;
                assistantRow.appendChild(actions);

                messagesContainer.insertBefore(assistantRow, typingIndicator);
                renderMarkdown(assistantBubble);
                scrollToBottom();

                // Update session title in sidebar and header if updated
                if (data.session_title) {
                    const activeNavTitle = document.querySelector('.session-link.active .session-title');
                    if (activeNavTitle) activeNavTitle.textContent = data.session_title;
                    const activeHeaderTitle = document.getElementById('activeHeaderTitle');
                    if (activeHeaderTitle) activeHeaderTitle.textContent = data.session_title;
                }
            })
            .catch(err => {
                typingIndicator.classList.add('d-none');
                input.disabled = false;
                sendBtn.disabled = false;
                input.focus();

                window.showToast(err.message || 'Failed to send message. Please try again.', 'danger');

                const assistantRow = document.createElement('div');
                assistantRow.className = 'message-row assistant-row';
                const assistantBubble = document.createElement('div');
                assistantBubble.className = 'message-bubble message-assistant text-danger';
                assistantBubble.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Error:</strong> ${err.message || 'Failed to send message. Please try again.'}`;
                assistantRow.appendChild(assistantBubble);
                messagesContainer.insertBefore(assistantRow, typingIndicator);
                scrollToBottom();
            });
        });
    });
</script>
@endif
@endpush
