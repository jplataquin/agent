<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>System Coherence Doctor Report - {{ config('app.name', 'Company AI Agent') }}</title>

    <!-- Immediate Theme Application (avoids flash) -->
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

        /* Dark / Light Theme */
        [data-bs-theme="dark"] body {
            background-color: #0f172a;
            color: #f1f5f9;
        }
        [data-bs-theme="light"] body {
            background-color: #ffffff;
            color: #0f172a;
        }

        .report-wrapper {
            max-width: 920px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 5rem 1.5rem;
        }

        .header-bar {
            border-bottom: 1px solid var(--bs-border-color);
            padding-bottom: 1.25rem;
            margin-bottom: 2rem;
        }

        /* Markdown Styles */
        .markdown-body {
            font-size: 1.05rem;
        }
        .markdown-body h1, .markdown-body h2, .markdown-body h3, .markdown-body h4, .markdown-body h5, .markdown-body h6 {
            margin-top: 1.85rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .markdown-body h1 {
            font-size: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .markdown-body h2 {
            font-size: 1.5rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .markdown-body h3 { font-size: 1.25rem; }
        .markdown-body h4 { font-size: 1.1rem; }
        .markdown-body p { margin-bottom: 1rem; }
        .markdown-body ul, .markdown-body ol {
            padding-left: 1.75rem;
            margin-bottom: 1.25rem;
        }
        .markdown-body li {
            margin-bottom: 0.35rem;
        }
        .markdown-body blockquote {
            border-left: 4px solid var(--bs-danger, #dc3545);
            padding: 0.65rem 1.25rem;
            margin: 1.25rem 0;
            color: var(--bs-secondary-color, #64748b);
            background-color: var(--bs-tertiary-bg);
            border-radius: 0 6px 6px 0;
        }
        .markdown-body table {
            width: 100%;
            margin-bottom: 1.5rem;
            border-collapse: collapse;
        }
        .markdown-body th, .markdown-body td {
            border: 1px solid var(--bs-border-color);
            padding: 0.6rem 0.9rem;
        }
        .markdown-body th {
            background-color: var(--bs-tertiary-bg);
            font-weight: 600;
        }
        .markdown-body pre {
            background-color: #1e293b;
            color: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 1rem 0;
        }
        .markdown-body code {
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 0.9em;
        }
        .markdown-body :not(pre) > code {
            background-color: var(--bs-tertiary-bg);
            color: var(--bs-danger);
            padding: 0.2em 0.4em;
            border-radius: 4px;
        }

        /* KaTeX formulas */
        .katex-display {
            overflow-x: auto;
            overflow-y: hidden;
            padding: 6px 0;
            margin: 0.75rem 0;
        }
        .math-block {
            overflow-x: auto;
            overflow-y: hidden;
            padding: 4px 0;
        }

        /* Print Mode */
        @media print {
            .no-print {
                display: none !important;
            }
            .report-wrapper {
                padding: 0;
                max-width: 100%;
            }
            body {
                background: #fff !important;
                color: #000 !important;
            }
            .markdown-body pre {
                background: #f1f5f9 !important;
                color: #0f172a !important;
            }
        }
    </style>
</head>
<body>
    <div class="report-wrapper">
        <!-- Header & Action Toolbar (No Navigation) -->
        <div class="header-bar d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-2 py-1">
                        <i class="bi bi-heart-pulse me-1"></i> Doctor Diagnostic
                    </span>
                    @if(isset($provider) && $provider)
                        <span class="badge bg-body-secondary text-body font-monospace px-2 py-1">
                            {{ $provider->name }} ({{ $provider->model }})
                        </span>
                    @endif
                    <span class="badge bg-body-secondary text-secondary font-monospace px-2 py-1">
                        {{ now()->format('Y-m-d H:i') }} UTC
                    </span>
                </div>
                <h3 class="fw-bold mb-0">System Coherence Doctor Report</h3>
                <p class="text-muted small mb-0">Autonomous diagnostic evaluation of Master Prompts, Active Skills, and Declared Tools</p>
            </div>

            <!-- Actions Bar -->
            <div class="d-flex align-items-center gap-2 no-print">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="copyReportBtn" title="Copy raw markdown report">
                    <i class="bi bi-clipboard me-1"></i> <span>Copy Report</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="window.print()" title="Print report or save as PDF">
                    <i class="bi bi-printer me-1"></i> Print / PDF
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle p-2" id="themeToggleBtn" title="Toggle Dark/Light Mode" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="bi bi-moon-stars theme-icon"></i>
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="window.close()" title="Close this tab">
                    <i class="bi bi-x-lg me-1"></i> Close
                </button>
            </div>
        </div>

        <!-- Rendered Report Container -->
        <div id="reportContainer" class="markdown-body"></div>

        <!-- Raw Report Data Source -->
        <textarea id="rawReportData" class="d-none">{!! e($report) !!}</textarea>

        <!-- Toast Notification (Top Right) -->
        <div class="position-fixed bottom-0 end-0 p-3 no-print" style="z-index: 1080;">
            <div id="copyToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle me-1"></i> Doctor report copied to clipboard!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Markdown Parser & Highlight.js -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <!-- KaTeX for LaTeX Math formula parsing -->
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Configure Marked to support LaTeX math expressions
            if (typeof marked !== 'undefined' && typeof katex !== 'undefined') {
                const mathExtension = {
                    extensions: [
                        {
                            name: 'mathBlock',
                            level: 'block',
                            start(src) {
                                const match = src.match(/(\$\$|\\\[|\\begin\{)/);
                                return match ? match.index : -1;
                            },
                            tokenizer(src) {
                                const dollarMatch = src.match(/^\$\$([\s\S]+?)\$\$(?:\n|$)/);
                                if (dollarMatch) {
                                    return {
                                        type: 'mathBlock',
                                        raw: dollarMatch[0],
                                        text: dollarMatch[1].trim(),
                                        displayMode: true
                                    };
                                }
                                const bracketMatch = src.match(/^\\\[([\s\S]+?)\\\](?:\n|$)/);
                                if (bracketMatch) {
                                    return {
                                        type: 'mathBlock',
                                        raw: bracketMatch[0],
                                        text: bracketMatch[1].trim(),
                                        displayMode: true
                                    };
                                }
                                const envMatch = src.match(/^(\\begin\{([a-zA-Z0-9*]+)\}[\s\S]+?\\end\{\2\})(?:\n|$)/);
                                if (envMatch) {
                                    return {
                                        type: 'mathBlock',
                                        raw: envMatch[0],
                                        text: envMatch[1].trim(),
                                        displayMode: true
                                    };
                                }
                            },
                            renderer(token) {
                                try {
                                    return '<div class="math-block my-2 overflow-auto text-center">' +
                                        katex.renderToString(token.text, { displayMode: true, throwOnError: false }) +
                                        '</div>\n';
                                } catch (e) {
                                    return '<pre class="math-error">' + token.raw + '</pre>';
                                }
                            }
                        },
                        {
                            name: 'mathInline',
                            level: 'inline',
                            start(src) {
                                let pos = 0;
                                while (pos < src.length) {
                                    const sub = src.slice(pos);
                                    const m = sub.match(/(\$\$|\\\(|\\\[|\$)/);
                                    if (!m) return -1;
                                    const matchIndex = pos + m.index;
                                    if (m[0] === '$' && matchIndex > 0 && src[matchIndex - 1] === '\\') {
                                        pos = matchIndex + 1;
                                        continue;
                                    }
                                    return matchIndex;
                                }
                                return -1;
                            },
                            tokenizer(src) {
                                const doubleDollarMatch = src.match(/^\$\$((?:\\.|[^\\\n])*?)\$\$/);
                                if (doubleDollarMatch) {
                                    return {
                                        type: 'mathInline',
                                        raw: doubleDollarMatch[0],
                                        text: doubleDollarMatch[1].trim(),
                                        displayMode: true
                                    };
                                }
                                const bracketMatch = src.match(/^\\\[((?:\\.|[^\\\]])*?)\\\]/);
                                if (bracketMatch) {
                                    return {
                                        type: 'mathInline',
                                        raw: bracketMatch[0],
                                        text: bracketMatch[1].trim(),
                                        displayMode: true
                                    };
                                }
                                const parenMatch = src.match(/^\\\(((?:\\.|[^\\\)])*?)\\\)/);
                                if (parenMatch) {
                                    return {
                                        type: 'mathInline',
                                        raw: parenMatch[0],
                                        text: parenMatch[1].trim(),
                                        displayMode: false
                                    };
                                }
                                const singleDollarMatch = src.match(/^\$((?:\\\$|[^\s$\n])(?:\\\$|[^\n$])*?(?:\\\$|[^\s$\n]))\$/);
                                if (singleDollarMatch) {
                                    return {
                                        type: 'mathInline',
                                        raw: singleDollarMatch[0],
                                        text: singleDollarMatch[1].trim(),
                                        displayMode: false
                                    };
                                }
                                const singleCharMatch = src.match(/^\$([a-zA-Z0-9+\-=<>\\])\$/);
                                if (singleCharMatch) {
                                    return {
                                        type: 'mathInline',
                                        raw: singleCharMatch[0],
                                        text: singleCharMatch[1].trim(),
                                        displayMode: false
                                    };
                                }
                            },
                            renderer(token) {
                                try {
                                    return katex.renderToString(token.text, { displayMode: token.displayMode, throwOnError: false });
                                } catch (e) {
                                    return token.raw;
                                }
                            }
                        }
                    ]
                };
                marked.use(mathExtension);
            }

            // 2. Render Markdown content
            const raw = document.getElementById('rawReportData').value;
            const container = document.getElementById('reportContainer');
            if (raw && container && typeof marked !== 'undefined') {
                container.innerHTML = marked.parse(raw);
                container.querySelectorAll('pre code').forEach((block) => {
                    hljs.highlightElement(block);
                });
                if (typeof renderMathInElement === 'function') {
                    renderMathInElement(container, {
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
            }

            // 3. Copy to Clipboard
            const copyBtn = document.getElementById('copyReportBtn');
            const copyToastEl = document.getElementById('copyToast');
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    navigator.clipboard.writeText(raw).then(function() {
                        const originalHtml = copyBtn.innerHTML;
                        copyBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Copied!';
                        copyBtn.classList.replace('btn-outline-secondary', 'btn-success');
                        if (window.bootstrap && window.bootstrap.Toast && copyToastEl) {
                            const toast = window.bootstrap.Toast.getOrCreateInstance(copyToastEl);
                            toast.show();
                        }
                        setTimeout(function() {
                            copyBtn.innerHTML = originalHtml;
                            copyBtn.classList.replace('btn-success', 'btn-outline-secondary');
                        }, 2000);
                    }).catch(function(err) {
                        alert('Failed to copy report: ' + err.message);
                    });
                });
            }

            // 4. Dark/Light Theme Toggle
            const themeBtn = document.getElementById('themeToggleBtn');
            if (themeBtn) {
                const icon = themeBtn.querySelector('.theme-icon');
                const updateThemeIcon = function(theme) {
                    if (theme === 'dark') {
                        icon.className = 'bi bi-sun-fill theme-icon text-warning';
                    } else {
                        icon.className = 'bi bi-moon-stars theme-icon';
                    }
                };

                const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
                updateThemeIcon(currentTheme);

                themeBtn.addEventListener('click', function() {
                    const activeTheme = document.documentElement.getAttribute('data-bs-theme');
                    const newTheme = activeTheme === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('company_ai_theme', newTheme);
                    updateThemeIcon(newTheme);
                });
            }
        });
    </script>
</body>
</html>
