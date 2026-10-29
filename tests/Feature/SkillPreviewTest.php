<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $appPath = dirname(__DIR__, 2) . '/app';
        $rdi = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
        $classMap = [];
        foreach ($rdi as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $rel = substr($file->getPathname(), strlen($appPath));
                $class = 'App' . str_replace('/', '\\', substr($rel, 0, -4));
                $classMap[$class] = $file->getPathname();
            }
        }
        $loader = require dirname(__DIR__, 2) . '/vendor/autoload.php';
        $loader->addClassMap($classMap);

        parent::setUp();

        config(['view.paths' => [dirname(__DIR__, 2) . '/resources/views']]);
        if (app()->bound('view.finder')) {
            app()->forgetInstance('view.finder');
        }
        \Illuminate\Support\Facades\View::clearResolvedInstances();
    }

    public function test_guest_cannot_access_skill_preview(): void
    {
        $skill = Skill::create([
            'name' => 'Test Skill',
            'slug' => 'test-skill',
            'description' => 'Test Description',
            'instructions' => 'Follow these instructions.',
            'is_active' => true,
        ]);

        $response = $this->get(route('admin.skills.preview', $skill));
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_skill_preview(): void
    {
        $regularUser = User::factory()->create([
            'role' => User::ROLE_REGULAR,
        ]);

        $skill = Skill::create([
            'name' => 'Test Skill',
            'slug' => 'test-skill',
            'description' => 'Test Description',
            'instructions' => 'Follow these instructions.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($regularUser)->get(route('admin.skills.preview', $skill));
        $response->assertStatus(403);
    }

    public function test_admin_edit_skill_page_has_preview_button(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
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

        // Ensure preview button is present, links to preview route, and targets _blank
        $response->assertSee(route('admin.skills.preview', $skill));
        $response->assertSee('Preview Markdown');
        $response->assertSee('target="_blank"', false);
        $response->assertSee('id="previewSkillBtn"', false);
    }

    public function test_admin_can_preview_skill_markdown_full_page_with_no_navigation_or_ui(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $skill = Skill::create([
            'name' => 'Network Troubleshooting Skill',
            'slug' => 'network-troubleshooting',
            'description' => 'Resolves router, VPN, and latency issues.',
            'instructions' => "Run `traceroute` and check gateway ping before escalating.",
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.skills.preview', $skill));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Verify the entire skill markdown text elements are included
        $this->assertStringContainsString('Network Troubleshooting Skill', $content);
        $this->assertStringContainsString('Resolves router, VPN, and latency issues.', $content);
        $this->assertStringContainsString('Run `traceroute` and check gateway ping before escalating.', $content);

        // Verify NO navigation bars or administrative UI exist
        $this->assertStringNotContainsString('<nav class="navbar', $content);
        $this->assertStringNotContainsString('navbar-brand', $content);
        $this->assertStringNotContainsString('Admin Console', $content);
        $this->assertStringNotContainsString('pwaInstallToast', $content);
        $this->assertStringNotContainsString('pwaOfflineToast', $content);

        // Verify full page container and markdown rendering elements exist
        $response->assertSee('id="rendered-view"', false);
        $response->assertSee('id="raw-view"', false);
        $response->assertSee('class="markdown-body"', false);
    }

    public function test_admin_can_preview_unsaved_skill_form_changes_via_post(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $skill = Skill::create([
            'name' => 'Original Skill Name',
            'slug' => 'original-skill',
            'description' => 'Original description.',
            'instructions' => 'Original instructions.',
            'is_active' => true,
        ]);

        // Simulate submitting modified, unsaved form contents from edit page
        $response = $this->actingAs($admin)->post(route('admin.skills.preview', $skill), [
            'name' => 'Draft Skill Title (Unsaved)',
            'description' => 'Draft description typed in the edit form.',
            'instructions' => "### Section 1\nUnsaved modified instructions for testing.",
        ]);

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('Draft Skill Title (Unsaved)', $content);
        $this->assertStringContainsString('Draft description typed in the edit form.', $content);
        $this->assertStringContainsString('Unsaved modified instructions for testing.', $content);

        // Database record must NOT be modified by preview
        $this->assertDatabaseHas('skills', [
            'id' => $skill->id,
            'name' => 'Original Skill Name',
        ]);

        // Still no navigation or UI
        $this->assertStringNotContainsString('<nav class="navbar', $content);
        $this->assertStringNotContainsString('Admin Console', $content);
    }
}
