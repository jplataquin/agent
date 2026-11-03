<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use App\Services\AiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillTemplateTest extends TestCase
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
    }

    public function test_it_resolves_nested_skill_templates(): void
    {
        Skill::create([
            'name' => 'Sub Skill',
            'slug' => 'sub-skill',
            'description' => 'A sub skill',
            'instructions' => 'These are the sub skill instructions.',
            'is_active' => true,
        ]);

        $parentInstructions = "Main Instructions:\n{@sub-skill}\nEnd of main.";
        $resolved = Skill::resolveTemplate($parentInstructions);

        $this->assertStringContainsString('These are the sub skill instructions.', $resolved);
        $this->assertStringNotContainsString('{@sub-skill}', $resolved);
    }

    public function test_it_handles_circular_references_gracefully(): void
    {
        Skill::create([
            'name' => 'Skill A',
            'slug' => 'skill-a',
            'description' => 'Skill A',
            'instructions' => 'Include B: {@skill-b}',
            'is_active' => true,
        ]);

        Skill::create([
            'name' => 'Skill B',
            'slug' => 'skill-b',
            'description' => 'Skill B',
            'instructions' => 'Include A: {@skill-a}',
            'is_active' => true,
        ]);

        $resolved = Skill::resolveTemplate('{@skill-a}');

        $this->assertStringContainsString('Circular reference detected for skill: skill-a', $resolved);
    }

    public function test_it_handles_missing_skills(): void
    {
        $resolved = Skill::resolveTemplate('Include non-existent: {@non-existent-skill}');

        $this->assertStringContainsString('[Skill not found: non-existent-skill]', $resolved);
    }

    public function test_ai_agent_service_builds_prompt_with_resolved_skills(): void
    {
        Skill::create([
            'name' => 'Base Context',
            'slug' => 'base-context',
            'description' => 'Base guidelines',
            'instructions' => 'Always be polite.',
            'is_active' => false, // Can be inactive as a partial template!
        ]);

        Skill::create([
            'name' => 'Customer Support',
            'slug' => 'customer-support',
            'description' => 'Support skill',
            'instructions' => "Support Guidelines:\n{@base-context}",
            'is_active' => true,
        ]);

        /** @var AiAgentService $service */
        $service = app(AiAgentService::class);
        $prompt = $service->buildSystemPrompt();

        $this->assertStringContainsString('Always be polite.', $prompt);
        $this->assertStringNotContainsString('{@base-context}', $prompt);
    }

    public function test_resolve_preview_endpoint_returns_resolved_markdown(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Skill::create([
            'name' => 'Included Skill',
            'slug' => 'included-skill',
            'description' => 'To be included',
            'instructions' => 'Included body content.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.skills.resolve-preview'), [
            'name' => 'New Skill',
            'description' => 'My skill',
            'instructions' => 'Here is the inclusion: {@included-skill}',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['markdown']);
        $this->assertStringContainsString('Included body content.', $response->json('markdown'));
        $this->assertStringNotContainsString('{@included-skill}', $response->json('markdown'));
    }
}
