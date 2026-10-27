<?php

namespace Tests\Feature;

use App\Models\LlmProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_gemini_diagnostics_page_is_accessible_without_auth()
    {
        $response = $this->get('/diagnostics/gemini');

        $response->assertStatus(200);
        $response->assertSee('Gemini 3 Diagnostics');
        $response->assertSee('Execute Gemini 3 Diagnostic');
    }

    public function test_gemini_diagnostic_status_api_returns_json()
    {
        $response = $this->getJson('/api/diagnostics/gemini/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'active_provider',
            'total_providers',
            'server_environment',
        ]);
    }

    public function test_gemini_diagnostic_run_executes_with_mocked_gemini_3_pipeline()
    {
        $provider = LlmProvider::create([
            'name' => 'Gemini 3 Production',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyRealApiKeyTest123',
            'model' => 'gemini-3.8-flash',
            'temperature' => 0.2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $mockSig = 'mock_crypto_thought_signature_xyz987';

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                // 1. Basic Connectivity
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Gemini 3 Diagnostic OK (gemini-3.8-flash)'],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                    'usageMetadata' => ['promptTokenCount' => 15, 'candidatesTokenCount' => 8],
                ], 200)
                // 2. Tool Calling with Thought Signature
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['thought' => true, 'text' => 'Reasoning about listing active projects...'],
                                    [
                                        'functionCall' => [
                                            'name' => 'list_projects',
                                            'args' => ['status' => 'ACTV'],
                                        ],
                                        'thought_signature' => $mockSig,
                                    ],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                ], 200)
                // 3. Stateful Round-Trip with Tool Output
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Here are our active projects: Metro Expressway Expansion.'],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $response = $this->postJson('/api/diagnostics/gemini/run', [
            'provider_id' => $provider->id,
            'model' => 'gemini-3.8-flash',
            'thinking_level' => 'high',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['summary']['all_passed']);
        $this->assertEquals('gemini-3.8-flash', $data['target_model']);

        // Check 1
        $this->assertEquals('passed', $data['checks']['connectivity']['status']);
        $this->assertStringContainsString('Diagnostic OK', $data['checks']['connectivity']['reply']);

        // Check 2
        $this->assertEquals('passed', $data['checks']['tool_call']['status']);
        $this->assertEquals('list_projects', $data['checks']['tool_call']['tool_invoked']);
        $this->assertTrue($data['checks']['tool_call']['has_thought_signature']);
        $this->assertEquals($mockSig, $data['checks']['tool_call']['tool_call_data']['thought_signature']);

        // Check 3
        $this->assertEquals('passed', $data['checks']['round_trip']['status']);
        $this->assertStringContainsString('Metro Expressway', $data['checks']['round_trip']['synthesized_response']);
    }

    public function test_artisan_gemini_diagnose_command_runs_successfully()
    {
        $provider = LlmProvider::create([
            'name' => 'Gemini 3 Mock Provider',
            'provider_type' => 'gemini',
            'api_key' => 'mock-gemini-key',
            'model' => 'gemini-3.8-flash',
            'temperature' => 0.2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->artisan('gemini:diagnose', ['--json' => true])
            ->assertSuccessful();

        $this->artisan('gemini:diagnose')
            ->expectsOutputToContain('Gemini 3 Diagnostics')
            ->expectsOutputToContain('ALL DIAGNOSTIC CHECKS PASSED')
            ->assertSuccessful();
    }
}
