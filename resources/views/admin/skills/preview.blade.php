<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ !empty($name) ? $name . ' - Markdown Preview' : ($skill->name ? $skill->name . ' - Markdown Preview' : 'Skill Markdown Preview') }}</title>

    <!-- Immediate Theme Application (matches saved theme, avoids flash) -->
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

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Syntax Highlighting & Math -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark-dimmed.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">

    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.65;
            min-height: 100vh;
        }

        /* Dark / Light Theme Palette */
        [data-bs-theme="dark"] body {
            background-color: #0f172a;
            color: #f1f5f9;
        }
        [data-bs-theme="light"] body {
            background-color: #ffffff;
            color: #0f172a;
        }

        .preview-wrapper {
            max-width: 880px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 5rem 1.5rem;
        }

        /* Full page Markdown styling */
        .markdown-body {
            font-size: 1.05rem;
        }
        .markdown-body h1, .markdown-body h2, .markdown-body h3, .markdown-body h4, .markdown-body h5, .markdown-body h6 {
            margin-top: 1.75rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .markdown-body h1 {
            font-size: 2.1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .markdown-body h2 {
            font-size: 1.55rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .markdown-body h3 { font-size: 1.3rem; }
        .markdown-body h4 { font-size: 1.15rem; }
        .markdown-body p { margin-bottom: 1rem; }
        .markdown-body ul, .markdown-body ol {
            padding-left: 1.75rem;
            margin-bottom: 1.25rem;
        }
        .markdown-body li {
            margin-bottom: 0.35rem;
        }
        .markdown-body blockquote {
            border-left: 4px solid var(--bs-primary, #0d6efd);
            padding: 0.65rem 1.25rem;
            margin: 1.25rem 0;
            color: var(--bs-secondary-color, #64748b);
            background-color: var(--bs-tertiary-bg);
            border-radius: 0 6px 6px 0;
        }
        .markdown-body blockquote p:last-child {
            margin-bottom: 0;
        }
        .markdown-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.95rem;
        }
        .markdown-body th, .markdown-body td {
            border: 1px solid var(--bs-border-color);
            padding: 8px 14px;
        }
        .markdown-body th {
            background-color: var(--bs-tertiary-bg);
            font-weight: 600;
        }
        .markdown-body hr {
            margin: 2rem 0;
            border-color: var(--bs-border-color);
            opacity: 0.35;
        }

        /* Inline code */
        .markdown-body :not(pre) > code {
            padding: 0.2em 0.45em;
            background-color: var(--bs-tertiary-bg);
            border-radius: 4px;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 0.88em;
            color: var(--bs-primary-text-emphasis);
        }

        /* Code Blocks */
        .code-block-wrapper {
            position: relative;
            margin: 1.25rem 0;
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
            padding: 6px 14px;
            font-size: 0.78rem;
            font-family: monospace;
        }
        .code-copy-btn {
            background: transparent;
            border: none;
            color: #a6adc8;
            cursor: pointer;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .code-copy-btn:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .markdown-body pre {
            margin: 0;
            padding: 1rem 1.25rem;
            overflow-x: auto;
            background: #1e1e2e;
            color: #f8f8f2;
        }
        .markdown-body pre code {
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 0.9em;
            background: transparent;
            padding: 0;
        }

        /* Raw Markdown View */
        #raw-view {
            white-space: pre-wrap;
            word-break: break-word;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 0.95rem;
            line-height: 1.6;
            padding: 1.5rem;
            border-radius: 8px;
            background-color: var(--bs-tertiary-bg);
            border: 1px solid var(--bs-border-color);
        }

        /* Floating Minimal Controls (print-hidden) */
        .floating-controls {
            position: fixed;
            top: 1rem;
            right: 1.25rem;
            z-index: 1060;
        }
        .floating-controls .btn-group {
            backdrop-filter: blur(8px);
            background-color: var(--bs-body-bg);
        }

        @media print {
            .no-print { display: none !important; }
            .preview-wrapper { padding: 0 !important; max-width: 100% !important; }
        }
    </style>
</head>
<body>
    <!-- Subtle Floating Controls for Reader Convenience (No site UI/nav) -->
    <div class="floating-controls no-print">
        <div class="btn-group btn-group-sm shadow-sm rounded-pill border bg-body" role="group">
            <button type="button" class="btn btn-outline-secondary border-0 rounded-start-pill px-3" id="toggleRawBtn" title="Switch between Rendered and Raw Markdown">
                <i class="bi bi-code-slash me-1"></i> <span id="toggleRawLabel">Raw</span>
            </button>
            <button type="button" class="btn btn-outline-secondary border-0 px-3" id="copyAllBtn" title="Copy Raw Markdown">
                <i class="bi bi-clipboard me-1" id="copyIcon"></i> <span id="copyLabel">Copy</span>
            </button>
            <button type="button" class="btn btn-outline-secondary border-0 rounded-end-pill px-3 theme-toggle-btn" title="Toggle Theme">
                <i class="bi bi-moon-stars theme-icon"></i>
            </button>
        </div>
    </div>

    <!-- Raw text data container -->
    <textarea id="raw-markdown-data" style="display: none;">{{ $markdown }}</textarea>

    <!-- Main Content Container: Full Page, No Navigation, No UI -->
    <main class="preview-wrapper">
        <!-- Rendered Markdown Container -->
        <article id="rendered-view" class="markdown-body">
            <noscript>
                <pre class="p-3 bg-body-tertiary rounded">{{ $markdown }}</pre>
            </noscript>
        </article>

        <!-- Raw Text View (hidden by default) -->
        <pre id="raw-view" style="display: none;">{{ $markdown }}</pre>
    </main>

    <!-- Markdown Parser, Highlight.js & KaTeX -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rawMarkdown = document.getElementById('raw-markdown-data').value;
            const renderedView = document.getElementById('rendered-view');
            const rawView = document.getElementById('raw-view');
            const toggleRawBtn = document.getElementById('toggleRawBtn');
            const toggleRawLabel = document.getElementById('toggleRawLabel');
            const copyAllBtn = document.getElementById('copyAllBtn');
            const copyIcon = document.getElementById('copyIcon');
            const copyLabel = document.getElementById('copyLabel');

            let isRaw = false;

            // 1. Render Markdown with Marked
            if (typeof marked !== 'undefined') {
                // Configure marked renderer for code blocks with headers & copy button
                const renderer = new marked.Renderer();
                const originalCode = renderer.code.bind(renderer);

                renderer.code = function(tokenOrCode, langOrUndefined) {
                    let code = typeof tokenOrCode === 'object' ? tokenOrCode.text : tokenOrCode;
                    let lang = typeof tokenOrCode === 'object' ? (tokenOrCode.lang || 'code') : (langOrUndefined || 'code');

                    let highlighted = code;
                    if (typeof hljs !== 'undefined' && lang && hljs.getLanguage(lang)) {
                        try {
                            highlighted = hljs.highlight(code, { language: lang }).value;
                        } catch (e) {
                            highlighted = hljs.highlightAuto(code).value;
                        }
                    } else if (typeof hljs !== 'undefined') {
                        try {
                            highlighted = hljs.highlightAuto(code).value;
                        } catch (e) {
                            highlighted = code;
                        }
                    }

                    const escapedCode = code.replace(/"/g, '&quot;');
                    return `<div class="code-block-wrapper">
                        <div class="code-header">
                            <span>${lang}</span>
                            <button class="code-copy-btn" onclick="copySnippet(this)" data-code="${escapedCode}">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <pre><code class="hljs ${lang}">${highlighted}</code></pre>
                    </div>`;
                };

                marked.use({ renderer });
                renderedView.innerHTML = marked.parse(rawMarkdown);
            } else {
                renderedView.innerHTML = `<pre>${rawMarkdown}</pre>`;
            }

            // 2. KaTeX Math rendering if applicable
            if (typeof renderMathInElement === 'function') {
                renderMathInElement(renderedView, {
                    delimiters: [
                        { left: '$$', right: '$$', display: true },
                        { left: '\\[', right: '\\]', display: true },
                        { left: '\\(', right: '\\)', display: false },
                        { left: '$', right: '$', display: false }
                    ],
                    throwOnError: false,
                    ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
                });
            }

            // 3. Toggle Raw / Rendered
            if (toggleRawBtn) {
                toggleRawBtn.addEventListener('click', function () {
                    isRaw = !isRaw;
                    if (isRaw) {
                        renderedView.style.display = 'none';
                        rawView.style.display = 'block';
                        toggleRawLabel.textContent = 'Rendered';
                    } else {
                        rawView.style.display = 'none';
                        renderedView.style.display = 'block';
                        toggleRawLabel.textContent = 'Raw';
                    }
                });
            }

            // 4. Copy Raw Markdown to clipboard
            if (copyAllBtn) {
                copyAllBtn.addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(rawMarkdown);
                        copyIcon.className = 'bi bi-check2 text-success me-1';
                        copyLabel.textContent = 'Copied!';
                        setTimeout(() => {
                            copyIcon.className = 'bi bi-clipboard me-1';
                            copyLabel.textContent = 'Copy';
                        }, 2000);
                    } catch (e) {
                        alert('Could not copy to clipboard.');
                    }
                });
            }

            // 5. Theme Toggle Logic
            const themeToggleBtn = document.querySelector('.theme-toggle-btn');
            const themeIcon = document.querySelector('.theme-icon');

            function updateThemeIcon(theme) {
                if (themeIcon) {
                    if (theme === 'dark') {
                        themeIcon.className = 'bi bi-sun-fill theme-icon text-warning';
                    } else {
                        themeIcon.className = 'bi bi-moon-stars theme-icon';
                    }
                }
            }

            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            updateThemeIcon(currentTheme);

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function () {
                    const activeTheme = document.documentElement.getAttribute('data-bs-theme');
                    const newTheme = activeTheme === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('company_ai_theme', newTheme);
                    updateThemeIcon(newTheme);
                });
            }
        });

        // Snippet copy helper
        window.copySnippet = function (btn) {
            const code = btn.getAttribute('data-code');
            if (code) {
                navigator.clipboard.writeText(code).then(() => {
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check2 text-success"></i> Copied!';
                    setTimeout(() => {
                        btn.innerHTML = originalHtml;
                    }, 2000);
                });
            }
        };
    </script>
</body>
</html>
