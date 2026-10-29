<?php

namespace Tests\Feature;

use App\Models\LlmProvider;
use App\Models\MasterPrompt;
use App\Models\Skill;
use App\Models\User;
use App\Services\Llm\LlmAdapterFactory;
use App\Services\Llm\LlmAdapterInterface;
use App\Services\Llm\LlmResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillDoctorTest extends TestCase
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
        $this->seed();

        config(['view.paths' => [dirname(__DIR__, 2).'/resources/views']]);
        app()->forgetInstance('view.finder');
        \Illuminate\Support\Facades\View::clearResolvedInstances();
    }

    public function test_admin_can_view_doctor_button_on_skills_page()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->get(route('admin.skills.index'));

        $response->assertStatus(200);
        $response->assertSee('Doctor');
        $response->assertSee('doctorModal');
    }

    public function test_non_admin_cannot_access_doctor_endpoint()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();

        $response = $this->actingAs($user)->postJson(route('admin.skills.doctor'));

        $response->assertStatus(403);
    }

    public function test_doctor_returns_error_if_no_active_llm_provider()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        LlmProvider::query()->update(['is_active' => false]);

        $response = $this->actingAs($admin)->postJson(route('admin.skills.doctor'));

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'No active LLM provider found. Please configure and activate a provider in the Admin Console.',
        ]);
    }

    public function test_doctor_queries_active_llm_and_returns_coherence_report()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        // Ensure an active default provider exists
        LlmProvider::query()->update(['is_default' => false, 'is_active' => false]);
        $provider = LlmProvider::create([
            'name' => 'Test OpenAI',
            'provider_type' => 'openai',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'is_active' => true,
            'is_default' => true,
        ]);

        // Create Master Prompt and Skill
        MasterPrompt::create([
            'title' => 'Company Master Prompt',
            'content' => 'Always answer politely and concisely.',
            'is_active' => true,
        ]);

        Skill::create([
            'name' => 'Data Analyst',
            'slug' => 'data-analyst',
            'instructions' => 'Provide detailed analytical breakdowns.',
            'is_active' => true,
        ]);

        // Mock the adapter
        $mockAdapter = $this->createMock(LlmAdapterInterface::class);
        $mockAdapter->expects($this->once())
            ->method('send')
            ->willReturn(new LlmResponse(
                content: "### Executive Summary\nConfiguration is mostly coherent with minor style differences.",
                promptTokens: 150,
                completionTokens: 50
            ));

        $mockFactory = $this->createMock(LlmAdapterFactory::class);
        $mockFactory->expects($this->once())
            ->method('make')
            ->with($this->callback(fn ($p) => $p->id === $provider->id))
            ->willReturn($mockAdapter);

        $this->app->instance(LlmAdapterFactory::class, $mockFactory);

        $response = $this->actingAs($admin)->postJson(route('admin.skills.doctor'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'report' => "### Executive Summary\nConfiguration is mostly coherent with minor style differences.",
        ]);
    }
}
