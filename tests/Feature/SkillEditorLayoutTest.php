<?php

namespace Tests\Feature;

use App\Models\McpServer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class SkillEditorLayoutTest extends TestCase
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

    public function test_admin_create_skill_page_has_two_pane_layout_and_mcp_explorer(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.skills.create'));
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
        $response->assertSee('id="skillInstructions"', false);
        $response->assertSee('id="mcpTreeSearch"', false);
        $response->assertSee('setPointerCapture', false);
        $response->assertSee('skillSuggestionBox', false);
    }

    public function test_admin_edit_skill_page_has_two_pane_layout_and_mcp_explorer(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Skill::create([
            'name' => 'Helper Skill',
            'slug' => 'helper-skill',
            'description' => 'Helpful routines.',
            'instructions' => 'Helper routines.',
            'is_active' => true,
        ]);

        $skill = Skill::create([
            'name' => 'Data Analyst Agent Skill',
            'slug' => 'data-analyst-agent-skill',
            'description' => 'Executes SQL and generates charts.',
            'instructions' => "## Analyst Instructions\nQuery only allowed views.",
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.skills.edit', $skill));
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
        $response->assertSee('id="skillInstructions"', false);
        $response->assertSee('id="mcpTreeSearch"', false);
        $response->assertSee('setPointerCapture', false);
        $response->assertSee('skillSuggestionBox', false);
        $response->assertSee('helper-skill', false);
    }

    public function test_mcp_explorer_displays_servers_and_tools(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        // Create an active custom MCP server with cached tools
        McpServer::create([
            'name' => 'Database Query MCP',
            'endpoint_url' => 'http://localhost:3000/sse',
            'auth_type' => 'none',
            'is_active' => true,
            'tools_cache' => [
                [
                    'name' => 'execute_sql_query',
                    'description' => 'Executes read-only SQL queries on the data warehouse.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'The SQL statement to execute',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            'last_sync_status' => 'success',
            'last_sync_at' => now(),
        ]);

        $skill = Skill::create([
            'name' => 'SQL Specialist',
            'slug' => 'sql-specialist',
            'description' => 'Handles database tasks.',
            'instructions' => 'Use SQL query tools safely.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.skills.edit', $skill));
        $response->assertStatus(200);

        // Verify custom MCP server and its tool are rendered
        $response->assertSee('Database Query MCP');
        $response->assertSee('execute_sql_query');
        $response->assertSee('Executes read-only SQL queries on the data warehouse.');
        $response->assertSee('The SQL statement to execute');

        // Verify Sync button beside server name in node
        $response->assertSee('sync-single-server-btn', false);
    }

    public function test_mcp_tree_endpoint_returns_json_html(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.skills.mcp-tree'));
        $response->assertOk();
        $response->assertJsonStructure(['success', 'total_tools', 'html']);
    }

    public function test_sync_all_mcp_endpoint_executes_and_returns_json(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.skills.sync-all-mcp'));
        $response->assertOk();
        $response->assertJsonStructure(['success', 'synced_servers', 'total_tools', 'errors', 'html', 'message']);
    }
}
