<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmProviderFetchModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_gemini_models_via_api(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-1.5-flash',
                        'displayName' => 'Gemini 1.5 Flash',
                    ],
                    [
                        'name' => 'models/gemini-1.5-pro',
                        'displayName' => 'Gemini 1.5 Pro',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.llm-providers.fetch-models'), [
            'provider_type' => 'gemini',
            'api_key' => 'fake-gemini-key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'models' => [
                [
                    'id' => 'gemini-1.5-flash',
                    'name' => 'Gemini 1.5 Flash (gemini-1.5-flash)',
                ],
                [
                    'id' => 'gemini-1.5-pro',
                    'name' => 'Gemini 1.5 Pro (gemini-1.5-pro)',
                ],
            ],
        ]);
    }

    public function test_admin_can_fetch_openai_models_via_api(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-4o'],
                    ['id' => 'gpt-4o-mini'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.llm-providers.fetch-models'), [
            'provider_type' => 'openai',
            'api_key' => 'sk-fake-openai-key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'models' => [
                ['id' => 'gpt-4o', 'name' => 'gpt-4o'],
                ['id' => 'gpt-4o-mini', 'name' => 'gpt-4o-mini'],
            ],
        ]);
    }

    public function test_non_admin_cannot_fetch_models(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_REGULAR,
        ]);

        $response = $this->actingAs($user)->postJson(route('admin.llm-providers.fetch-models'), [
            'provider_type' => 'openai',
            'api_key' => 'sk-fake-openai-key',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_create_page_renders_fetch_models_elements(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.llm-providers.create'));
        $response->assertStatus(200);
        $response->assertSee('id="fetchModelsBtn"', false);
        $response->assertSee('id="modelSelectWrapper"', false);
        $response->assertSee('id="modelSelect"', false);
        $response->assertSee('id="modelFetchAlert"', false);
    }

    public function test_admin_edit_page_renders_fetch_models_elements(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $provider = \App\Models\LlmProvider::create([
            'name' => 'Test Gemini Provider',
            'provider_type' => 'gemini',
            'api_key' => 'fake-key',
            'model' => 'gemini-1.5-flash',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.llm-providers.edit', $provider));
        $response->assertStatus(200);
        $response->assertSee('id="fetchModelsBtn"', false);
        $response->assertSee('id="modelSelectWrapper"', false);
        $response->assertSee('id="modelSelect"', false);
        $response->assertSee('id="modelFetchAlert"', false);
    }
}
