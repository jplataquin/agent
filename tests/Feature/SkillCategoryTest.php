<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class SkillCategoryTest extends TestCase
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

    public function test_admin_can_view_skills_index_with_category_tabs_and_modal(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = SkillCategory::create([
            'name' => 'Customer Support',
            'slug' => 'customer-support',
            'description' => 'Support related skills',
        ]);
        Skill::create([
            'name' => 'Refund Processor',
            'slug' => 'refund-processor',
            'instructions' => 'Handle refund requests.',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.skills.index'));

        $response->assertStatus(200);
        $response->assertSee('Customer Support');
        $response->assertSee('Refund Processor');
        $response->assertSee('categoriesModal');
        $response->assertSee('Category:');
    }

    public function test_admin_can_create_skill_category(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.skill-categories.store'), [
            'name' => 'Engineering',
            'slug' => 'engineering',
            'description' => 'Development and architecture skills',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('skill_categories', [
            'name' => 'Engineering',
            'slug' => 'engineering',
            'description' => 'Development and architecture skills',
        ]);
    }

    public function test_admin_can_update_skill_category(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = SkillCategory::create([
            'name' => 'DevOps',
            'slug' => 'devops',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.skill-categories.update', $category), [
            'name' => 'Cloud & DevOps',
            'slug' => 'cloud-devops',
            'description' => 'Infrastructure management',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('skill_categories', [
            'id' => $category->id,
            'name' => 'Cloud & DevOps',
            'slug' => 'cloud-devops',
            'description' => 'Infrastructure management',
        ]);
    }

    public function test_admin_can_delete_skill_category_and_skills_become_uncategorized(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = SkillCategory::create([
            'name' => 'Temporary Category',
            'slug' => 'temporary-category',
        ]);
        $skill = Skill::create([
            'name' => 'Orphanable Skill',
            'slug' => 'orphanable-skill',
            'instructions' => 'Some instructions.',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.skill-categories.destroy', $category));

        $response->assertRedirect();
        $this->assertDatabaseMissing('skill_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('skills', [
            'id' => $skill->id,
            'category_id' => null,
        ]);
    }

    public function test_admin_can_create_skill_with_category(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $category = SkillCategory::create([
            'name' => 'Data Science',
            'slug' => 'data-science',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.skills.store'), [
            'name' => 'SQL Optimizer',
            'slug' => 'sql-optimizer',
            'category_id' => $category->id,
            'description' => 'Optimizes complex SQL queries',
            'instructions' => 'Analyze EXPLAIN plans and suggest indexes.',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.skills.index'));
        $this->assertDatabaseHas('skills', [
            'name' => 'SQL Optimizer',
            'category_id' => $category->id,
        ]);
    }

    public function test_admin_can_update_skills_assigned_category(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cat1 = SkillCategory::create(['name' => 'Frontend', 'slug' => 'frontend']);
        $cat2 = SkillCategory::create(['name' => 'Backend', 'slug' => 'backend']);

        $skill = Skill::create([
            'name' => 'API Designer',
            'slug' => 'api-designer',
            'instructions' => 'Design clean REST endpoints.',
            'category_id' => $cat1->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.skills.update', $skill), [
            'name' => 'API Designer',
            'slug' => 'api-designer',
            'category_id' => $cat2->id,
            'instructions' => 'Design clean REST endpoints.',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.skills.index'));
        $this->assertDatabaseHas('skills', [
            'id' => $skill->id,
            'category_id' => $cat2->id,
        ]);
    }

    public function test_skills_index_filters_by_category(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supportCat = SkillCategory::create(['name' => 'Support', 'slug' => 'support']);
        $salesCat = SkillCategory::create(['name' => 'Sales', 'slug' => 'sales']);

        $supportSkill = Skill::create([
            'name' => 'Helpdesk Triage',
            'slug' => 'helpdesk-triage',
            'instructions' => 'Triage incoming support tickets.',
            'category_id' => $supportCat->id,
            'is_active' => true,
        ]);

        $salesSkill = Skill::create([
            'name' => 'Lead Qualifier',
            'slug' => 'lead-qualifier',
            'instructions' => 'Qualify inbound sales leads.',
            'category_id' => $salesCat->id,
            'is_active' => true,
        ]);

        // Filter by Support
        $response = $this->actingAs($admin)->get(route('admin.skills.index', ['category' => 'support']));
        $response->assertStatus(200);
        $response->assertSee('Helpdesk Triage');
        $response->assertDontSee('Lead Qualifier');

        // Filter by Sales
        $response = $this->actingAs($admin)->get(route('admin.skills.index', ['category' => 'sales']));
        $response->assertStatus(200);
        $response->assertSee('Lead Qualifier');
        $response->assertDontSee('Helpdesk Triage');
    }

    public function test_non_admin_cannot_manage_skill_categories(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_REGULAR]);

        $response = $this->actingAs($user)->post(route('admin.skill-categories.store'), [
            'name' => 'Unauthorized Category',
        ]);
        $response->assertStatus(403);

        $category = SkillCategory::create(['name' => 'Protected', 'slug' => 'protected']);

        $response = $this->actingAs($user)->delete(route('admin.skill-categories.destroy', $category));
        $response->assertStatus(403);
    }
}
