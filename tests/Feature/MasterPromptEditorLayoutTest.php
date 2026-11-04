<?php

namespace Tests\Feature;

use App\Models\MasterPrompt;
use App\Models\McpServer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class MasterPromptEditorLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $appPath = dirname(__DIR__, 2).'/app';
        $rdi = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
        $classMap = [];
        foreach ($rdi as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $rel = substr($file->getPathname(), strlen($appPath));
                $class = 'App'.str_replace('/', '\\', substr($rel, 0, -4));
                $classMap[$class] = $file->getPathname();
            }
        }
        $loader = require dirname(__DIR__, 2).'/vendor/autoload.php';
        $loader->addClassMap($classMap);

        parent::setUp();

        config(['view.paths' => [dirname(__DIR__, 2).'/resources/views']]);
        if (app()->bound('view.finder')) {
            app()->forgetInstance('view.finder');
        }
        View::clearResolvedInstances();
    }

    public function test_admin_create_master_prompt_page_has_two_pane_layout_and_mcp_explorer(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.master-prompts.create'));
        $response->assertStatus(200);

        // Verify two-pane layout container & drag resizer
        $response->assertSee('skills-editor-wrapper', false);
        $response->assertSee('id="leftPane"', false);
        $response->assertSee('id="dragResizer"', false);
        $response->assertSee('id="rightPane"', false);

        // Verify full-width container class & default 70% larger left pane width
        $response->assertSee('container-fluid', false);
        $response->assertSee('width: 70%', false);

        // Verify MCP Explorer components & pointer event resizer logic
        $response->assertSee('MCP Explorer');
        $response->assertSee('id="masterPromptContent"', false);
        $response->assertSee('id="mcpTreeSearch"', false);
        $response->assertSee('setPointerCapture', false);
        $response->assertSee('skillSuggestionBox', false);
    }

    public function test_admin_edit_master_prompt_page_has_two_pane_layout_and_mcp_explorer(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Skill::create([
            'name' => 'Support Helper Skill',
            'slug' => 'support-helper',
            'description' => 'Helpful customer routines.',
            'instructions' => 'Follow customer support protocol.',
            'is_active' => true,
        ]);

        $prompt = MasterPrompt::create([
            'title' => 'Core AI Persona',
            'description' => 'Foundation agent system prompt.',
            'content' => "## Persona\nYou are the primary corporate assistant.",
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.master-prompts.edit', $prompt));
        $response->assertStatus(200);

        // Verify two-pane layout container & drag resizer
        $response->assertSee('skills-editor-wrapper', false);
        $response->assertSee('id="leftPane"', false);
        $response->assertSee('id="dragResizer"', false);
        $response->assertSee('id="rightPane"', false);

        // Verify full-width container class & default 70% larger left pane width
        $response->assertSee('container-fluid', false);
        $response->assertSee('width: 70%', false);

        // Verify MCP Explorer components & pointer event resizer logic
        $response->assertSee('MCP Explorer');
        $response->assertSee('id="masterPromptContent"', false);
        $response->assertSee('id="mcpTreeSearch"', false);
        $response->assertSee('setPointerCapture', false);
        $response->assertSee('skillSuggestionBox', false);
        $response->assertSee('support-helper', false);

        // Verify preview markdown button in header
        $response->assertSee(route('admin.master-prompts.preview', $prompt));
        $response->assertSee('Preview Markdown');
        $response->assertSee('id="previewPromptBtn"', false);
    }

    public function test_mcp_explorer_displays_servers_and_tools_on_master_prompt_editor(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        McpServer::create([
            'name' => 'Inventory System MCP',
            'endpoint_url' => 'http://localhost:3000/sse',
            'auth_type' => 'none',
            'is_active' => true,
            'tools_cache' => [
                [
                    'name' => 'check_inventory_stock',
                    'description' => 'Checks current warehouse item stock levels.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'sku' => [
                                'type' => 'string',
                                'description' => 'Product SKU code',
                            ],
                        ],
                        'required' => ['sku'],
                    ],
                ],
            ],
            'last_sync_status' => 'success',
            'last_sync_at' => now(),
        ]);

        $prompt = MasterPrompt::create([
            'title' => 'Warehouse Bot',
            'description' => 'Handles logistics prompts.',
            'content' => 'Provide real-time stock balances.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.master-prompts.edit', $prompt));
        $response->assertStatus(200);

        // Verify custom MCP server and tool are rendered
        $response->assertSee('Inventory System MCP');
        $response->assertSee('check_inventory_stock');
        $response->assertSee('Checks current warehouse item stock levels.');
        $response->assertSee('Product SKU code');

        // Verify Sync button beside server name in node
        $response->assertSee('sync-single-server-btn', false);
    }

    public function test_master_prompt_mcp_tree_endpoint_returns_json_html(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.master-prompts.mcp-tree'));
        $response->assertOk();
        $response->assertJsonStructure(['success', 'total_tools', 'html']);
    }

    public function test_master_prompt_sync_all_mcp_endpoint_executes_and_returns_json(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.master-prompts.sync-all-mcp'));
        $response->assertOk();
        $response->assertJsonStructure(['success', 'synced_servers', 'total_tools', 'errors', 'html', 'message']);
    }

    public function test_master_prompt_resolve_preview_endpoint_returns_json_with_templating(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Skill::create([
            'name' => 'Corporate Compliance',
            'slug' => 'compliance-rules',
            'description' => 'Legal and compliance rules.',
            'instructions' => 'Never disclose confidential financial metrics.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.master-prompts.resolve-preview'), [
            'title' => 'Compliance Bot',
            'description' => 'Prompt for compliance bot.',
            'content' => "## Instructions\nAlways follow {@compliance-rules}",
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['markdown']);

        $markdown = $response->json('markdown');
        $this->assertStringContainsString('# Compliance Bot', $markdown);
        $this->assertStringContainsString('> Prompt for compliance bot.', $markdown);
        $this->assertStringContainsString('Never disclose confidential financial metrics.', $markdown);
    }

    public function test_admin_can_preview_master_prompt_markdown_full_page(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $prompt = MasterPrompt::create([
            'title' => 'Global AI Persona',
            'description' => 'Core persona for all inquiries.',
            'content' => 'Always remain courteous, helpful, and concise.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.master-prompts.preview', $prompt));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Verify title and prompt content exist
        $this->assertStringContainsString('Global AI Persona', $content);
        $this->assertStringContainsString('Core persona for all inquiries.', $content);
        $this->assertStringContainsString('Always remain courteous, helpful, and concise.', $content);

        // Verify NO navigation bars or administrative UI exist
        $this->assertStringNotContainsString('<nav class="navbar', $content);
        $this->assertStringNotContainsString('navbar-brand', $content);
        $this->assertStringNotContainsString('Admin Console', $content);

        // Verify full page container and markdown rendering elements exist
        $response->assertSee('id="rendered-view"', false);
        $response->assertSee('id="raw-view"', false);
        $response->assertSee('class="markdown-body"', false);
    }

    public function test_admin_can_preview_unsaved_master_prompt_form_changes_via_post(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $prompt = MasterPrompt::create([
            'title' => 'Original Prompt Title',
            'description' => 'Original description.',
            'content' => 'Original system prompt content.',
            'is_active' => true,
        ]);

        // Simulate submitting modified, unsaved form contents from edit page
        $response = $this->actingAs($admin)->post(route('admin.master-prompts.preview', $prompt), [
            'title' => 'Draft Unsaved Prompt Title',
            'description' => 'Draft description typed in form.',
            'content' => "### Section 1\nUnsaved modified content for testing.",
        ]);

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('Draft Unsaved Prompt Title', $content);
        $this->assertStringContainsString('Draft description typed in form.', $content);
        $this->assertStringContainsString('Unsaved modified content for testing.', $content);
    }
}
