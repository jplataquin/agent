import express from 'express';
import { chromium } from 'playwright';

const app = express();
const PORT = process.env.PORT || 3030;
const HOST = process.env.HOST || '0.0.0.0';

app.use(express.json({ limit: '10mb' }));

// Persistent Browser & Page context
let browser = null;
let context = null;
let page = null;

async function getBrowser() {
    if (!browser) {
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
            ],
        });
    }
    return browser;
}

async function getPage() {
    const b = await getBrowser();
    if (!context) {
        context = await b.newContext({
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            viewport: { width: 1280, height: 800 },
        });
    }
    if (!page || page.isClosed()) {
        page = await context.newPage();
    }
    return page;
}

async function resetBrowserContext() {
    if (page && !page.isClosed()) {
        await page.close().catch(() => {});
        page = null;
    }
    if (context) {
        await context.close().catch(() => {});
        context = null;
    }
}

/**
 * Extracts a structured snapshot of the current page including:
 * - Title and URL
 * - Key interactive elements (inputs, buttons, select, links) to help LLM formulate selectors
 * - Cleaned visible text
 */
async function extractPageSnapshot(activePage, maxLength = 6000) {
    const title = await activePage.title().catch(() => 'No title');
    const url = activePage.url();

    const info = await activePage.evaluate(() => {
        // Find visible interactive elements
        const elements = [];
        const isVisible = (el) => {
            const style = window.getComputedStyle(el);
            return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0' && el.offsetWidth > 0 && el.offsetHeight > 0;
        };

        // Inputs & Textareas
        document.querySelectorAll('input, textarea, select').forEach((el) => {
            if (!isVisible(el)) return;
            const tag = el.tagName.toLowerCase();
            const type = el.getAttribute('type') || 'text';
            if (type === 'hidden') return;
            const name = el.getAttribute('name');
            const id = el.id;
            const placeholder = el.getAttribute('placeholder');
            const value = el.value ? String(el.value).substring(0, 50) : '';
            const ariaLabel = el.getAttribute('aria-label');

            let desc = `<${tag} type="${type}"`;
            if (id) desc += ` id="${id}"`;
            if (name) desc += ` name="${name}"`;
            if (placeholder) desc += ` placeholder="${placeholder}"`;
            if (ariaLabel) desc += ` aria-label="${ariaLabel}"`;
            if (value) desc += ` value="${value}"`;
            desc += '>';
            elements.push(desc);
        });

        // Buttons
        document.querySelectorAll('button, [role="button"], input[type="submit"], input[type="button"]').forEach((el) => {
            if (!isVisible(el)) return;
            const text = (el.innerText || el.value || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').substring(0, 60);
            const id = el.id ? ` id="${el.id}"` : '';
            const type = el.getAttribute('type') ? ` type="${el.getAttribute('type')}"` : '';
            elements.push(`<button${id}${type}>${text}</button>`);
        });

        // Key prominent links (top 20)
        let linkCount = 0;
        document.querySelectorAll('a[href]').forEach((el) => {
            if (linkCount >= 20 || !isVisible(el)) return;
            const text = (el.innerText || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ');
            const href = el.getAttribute('href');
            if (text && text.length > 1 && !href.startsWith('javascript:')) {
                elements.push(`<a href="${href}">${text.substring(0, 50)}</a>`);
                linkCount++;
            }
        });

        // Body visible text clean extraction
        const clone = document.body ? document.body.cloneNode(true) : null;
        if (!clone) return { elements, text: '' };

        const removeSelectors = ['script', 'style', 'noscript', 'svg', 'iframe'];
        removeSelectors.forEach((sel) => {
            clone.querySelectorAll(sel).forEach((node) => node.remove());
        });

        const rawText = clone.innerText || '';
        return {
            elements: elements.slice(0, 35),
            text: rawText,
        };
    }).catch(() => ({ elements: [], text: '' }));

    // Clean text lines
    const lines = info.text
        .split('\n')
        .map((l) => l.trim())
        .filter((l) => l.length > 0);
    let cleanedText = lines.join('\n');

    if (cleanedText.length > maxLength) {
        cleanedText = cleanedText.substring(0, maxLength) + `\n... [Content truncated at ${maxLength} characters]`;
    }

    let result = `Page Title: ${title}\nCurrent URL: ${url}\n`;

    if (info.elements && info.elements.length > 0) {
        result += `\nInteractive Elements:\n` + info.elements.map((el) => `  - ${el}`).join('\n') + `\n`;
    }

    result += `\nPage Content:\n${cleanedText}`;
    return result;
}

const TOOLS = [
    {
        name: 'browser_navigate',
        description: 'Navigate the headless browser to a specified URL, waiting for page load, and return the page title, URL, interactive elements, and content.',
        inputSchema: {
            type: 'object',
            properties: {
                url: {
                    type: 'string',
                    description: 'The target website URL to navigate to (e.g. https://news.ycombinator.com)',
                },
                wait_until: {
                    type: 'string',
                    description: 'When to consider navigation succeeded: "load", "domcontentloaded", or "networkidle" (default: "load")',
                    enum: ['load', 'domcontentloaded', 'networkidle'],
                },
                timeout: {
                    type: 'integer',
                    description: 'Timeout in milliseconds (default: 30000)',
                },
            },
            required: ['url'],
        },
    },
    {
        name: 'browser_click',
        description: 'Click an element or button on the page using a CSS selector or Playwright text locator (e.g. "button#submit", "text=Next", "a.nav-link"). Returns updated page snapshot.',
        inputSchema: {
            type: 'object',
            properties: {
                selector: {
                    type: 'string',
                    description: 'CSS selector or text locator of the element to click',
                },
                wait_after: {
                    type: 'integer',
                    description: 'Milliseconds to wait after click for DOM or network response (default: 1000)',
                },
            },
            required: ['selector'],
        },
    },
    {
        name: 'browser_type',
        description: 'Fill or type text into an input field or textarea on the active page.',
        inputSchema: {
            type: 'object',
            properties: {
                selector: {
                    type: 'string',
                    description: 'Selector of the input element (e.g. "input[name=q]", "#search", "textarea")',
                },
                text: {
                    type: 'string',
                    description: 'The text string to type into the field',
                },
                press_enter: {
                    type: 'boolean',
                    description: 'Whether to press Enter key immediately after typing (useful for search forms, default: false)',
                },
            },
            required: ['selector', 'text'],
        },
    },
    {
        name: 'browser_press_key',
        description: 'Press a keyboard key on the active page (e.g. "Enter", "Tab", "Escape", "ArrowDown", "PageDown").',
        inputSchema: {
            type: 'object',
            properties: {
                key: {
                    type: 'string',
                    description: 'Key name to press (e.g. "Enter", "Escape", "Tab")',
                },
            },
            required: ['key'],
        },
    },
    {
        name: 'browser_snapshot',
        description: 'Get a fresh snapshot of the active page including title, URL, visible interactive elements, and content.',
        inputSchema: {
            type: 'object',
            properties: {
                max_length: {
                    type: 'integer',
                    description: 'Maximum characters of body text to return (default: 6000)',
                },
            },
            required: [],
        },
    },
    {
        name: 'browser_evaluate',
        description: 'Safely execute a JavaScript expression in the browser page context and return the result as JSON.',
        inputSchema: {
            type: 'object',
            properties: {
                script: {
                    type: 'string',
                    description: 'JavaScript expression or code string to evaluate in page (e.g. "document.title", "window.location.href")',
                },
            },
            required: ['script'],
        },
    },
    {
        name: 'browser_reset',
        description: 'Reset the browser session, closing the current page and clearing all cookies and navigation state.',
        inputSchema: {
            type: 'object',
            properties: {},
            required: [],
        },
    },
];

// Health Check Endpoint
app.get(['/', '/health'], async (req, res) => {
    let pageUrl = null;
    let pageTitle = null;
    if (page && !page.isClosed()) {
        pageUrl = page.url();
        pageTitle = await page.title().catch(() => null);
    }

    res.json({
        status: 'ok',
        server: 'playwright-mcp-server',
        version: '1.0.0',
        browser: {
            launched: Boolean(browser),
            active_page: Boolean(page && !page.isClosed()),
            current_url: pageUrl,
            current_title: pageTitle,
        },
        tools_count: TOOLS.length,
    });
});

// MCP JSON-RPC 2.0 Handler
app.post(['/', '/mcp', '/rpc', '/api/mcp'], async (req, res) => {
    const { jsonrpc, id, method, params } = req.body || {};

    if (method === 'initialize') {
        return res.json({
            jsonrpc: '2.0',
            id: id ?? 1,
            result: {
                protocolVersion: '2024-11-05',
                serverInfo: {
                    name: 'playwright-browser-server',
                    version: '1.0.0',
                },
                capabilities: {
                    tools: {},
                },
            },
        });
    }

    if (method === 'tools/list') {
        return res.json({
            jsonrpc: '2.0',
            id: id ?? 1,
            result: {
                tools: TOOLS,
            },
        });
    }

    if (method === 'tools/call') {
        const toolName = params?.name;
        const args = params?.arguments || {};

        try {
            let outputText = '';

            switch (toolName) {
                case 'browser_navigate': {
                    const activePage = await getPage();
                    const targetUrl = args.url;
                    const waitUntil = args.wait_until || 'load';
                    const timeout = Number(args.timeout) || 30000;

                    await activePage.goto(targetUrl, {
                        waitUntil,
                        timeout,
                    });

                    // Wait a short moment for client-side JS rendering if needed
                    await activePage.waitForTimeout(500);
                    outputText = await extractPageSnapshot(activePage);
                    break;
                }

                case 'browser_click': {
                    const activePage = await getPage();
                    const selector = args.selector;
                    const waitAfter = Number(args.wait_after) || 1000;

                    await activePage.click(selector, { timeout: 10000 });
                    if (waitAfter > 0) {
                        await activePage.waitForTimeout(waitAfter);
                    }
                    outputText = `Clicked "${selector}".\n\nUpdated Page State:\n` + (await extractPageSnapshot(activePage));
                    break;
                }

                case 'browser_type': {
                    const activePage = await getPage();
                    const selector = args.selector;
                    const text = String(args.text ?? '');
                    const pressEnter = Boolean(args.press_enter);

                    await activePage.fill(selector, text, { timeout: 10000 });
                    if (pressEnter) {
                        await activePage.press(selector, 'Enter');
                        await activePage.waitForTimeout(1000);
                        outputText = `Typed "${text}" into "${selector}" and pressed Enter.\n\nUpdated Page State:\n` + (await extractPageSnapshot(activePage));
                    } else {
                        outputText = `Successfully typed text into "${selector}".`;
                    }
                    break;
                }

                case 'browser_press_key': {
                    const activePage = await getPage();
                    const key = args.key;
                    await activePage.keyboard.press(key);
                    await activePage.waitForTimeout(800);
                    outputText = `Pressed key "${key}".\n\nUpdated Page State:\n` + (await extractPageSnapshot(activePage));
                    break;
                }

                case 'browser_snapshot': {
                    const activePage = await getPage();
                    const maxLen = Number(args.max_length) || 6000;
                    outputText = await extractPageSnapshot(activePage, maxLen);
                    break;
                }

                case 'browser_evaluate': {
                    const activePage = await getPage();
                    const script = args.script;
                    const evalResult = await activePage.evaluate((code) => {
                        // eslint-disable-next-line no-eval
                        return window.eval(code);
                    }, script);

                    const serialized = typeof evalResult === 'object' ? JSON.stringify(evalResult, null, 2) : String(evalResult);
                    outputText = `Script evaluation result:\n${serialized}`;
                    break;
                }

                case 'browser_reset': {
                    await resetBrowserContext();
                    outputText = 'Browser context and pages have been reset to a clean state.';
                    break;
                }

                default:
                    return res.status(400).json({
                        jsonrpc: '2.0',
                        id: id ?? 1,
                        error: {
                            code: -32601,
                            message: `Unknown tool: ${toolName}`,
                        },
                    });
            }

            return res.json({
                jsonrpc: '2.0',
                id: id ?? 1,
                result: {
                    content: [
                        {
                            type: 'text',
                            text: outputText,
                        },
                    ],
                },
            });
        } catch (err) {
            return res.json({
                jsonrpc: '2.0',
                id: id ?? 1,
                result: {
                    content: [
                        {
                            type: 'text',
                            text: `Playwright execution error: ${err.message}`,
                        },
                    ],
                    isError: true,
                },
            });
        }
    }

    return res.status(404).json({
        jsonrpc: '2.0',
        id: id ?? 1,
        error: {
            code: -32601,
            message: `Method "${method}" not found.`,
        },
    });
});

// Graceful termination
const shutdown = async () => {
    try {
        if (browser) {
            await browser.close().catch(() => {});
        }
    } finally {
        process.exit(0);
    }
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

const server = app.listen(PORT, HOST, () => {
    console.log(`[Playwright MCP] Server listening on http://${HOST}:${PORT}`);
    console.log(`[Playwright MCP] Endpoint: http://${HOST}:${PORT}/mcp`);
});

export default app;
