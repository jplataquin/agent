<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\LlmProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatTest extends TestCase
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
        $this->seed();

        config(['view.paths' => [dirname(__DIR__, 2) . '/resources/views']]);
        app()->forgetInstance('view.finder');
        \Illuminate\Support\Facades\View::clearResolvedInstances();
    }

    public function test_user_can_access_chat_and_create_session()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();

        $response = $this->actingAs($user)->get('/chat');
        $response->assertStatus(200);

        $createResponse = $this->actingAs($user)->post('/chat');
        $createResponse->assertRedirect();

        $this->assertDatabaseHas('chat_sessions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_send_message_and_record_token_metric()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Test Query',
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/message", [
            'message' => 'What is the vacation policy?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'user_message',
            'ai_response',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'What is the vacation policy?',
        ]);

        // Verify token metric was recorded
        $this->assertDatabaseHas('token_metrics', [
            'user_id' => $user->id,
            'chat_session_id' => $session->id,
        ]);
    }

    public function test_chat_view_does_not_render_raw_mcp_tool_json_messages()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Project Query',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Please query our internal projects.',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [
                ['id' => 'call_1', 'name' => 'list_projects', 'arguments' => []],
            ],
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'tool',
            'content' => json_encode(['projects' => [['name' => 'Secret Internal Project', 'status' => 'ACTIVE']]]),
            'tool_call_id' => 'call_1',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Here are the active internal projects found.',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}");
        $response->assertStatus(200);

        // Raw tool JSON block must NOT be rendered in user chat
        $response->assertDontSee('MCP System Result:');
        $response->assertDontSee('Secret Internal Project');

        // Human-readable assistant reply must be visible
        $response->assertSee('Here are the active internal projects found.');
    }

    public function test_user_can_continue_conversation_after_tool_and_llm_reply()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Multi-turn Discussion',
        ]);

        // Prior turn 1 with tool call
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'First message',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [
                ['id' => 'call_99', 'name' => 'get_balance', 'arguments' => []],
            ],
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'tool',
            'content' => '{"balance": 500}',
            'tool_call_id' => 'call_99',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Your balance is 500 credits.',
        ]);

        // Follow-up message continuing the conversation
        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/message", [
            'message' => 'Can you break that down?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Can you break that down?',
        ]);
    }

    public function test_user_cannot_access_or_delete_another_users_session()
    {
        $userA = User::where('role', User::ROLE_REGULAR)->first();
        $userB = User::where('role', User::ROLE_ADMIN)->first();

        $sessionA = ChatSession::create([
            'user_id' => $userA->id,
            'title' => 'Private Session A',
        ]);

        // User B tries to view User A's session
        $response = $this->actingAs($userB)->get("/chat/{$sessionA->id}");
        $response->assertStatus(403);

        // User B tries to delete User A's session
        $deleteResponse = $this->actingAs($userB)->delete("/chat/{$sessionA->id}");
        $deleteResponse->assertStatus(403);
    }

    public function test_user_can_delete_own_session_via_json_request()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();

        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Session to be deleted',
        ]);

        $deleteResponse = $this->actingAs($user)->deleteJson("/chat/{$session->id}");
        $deleteResponse->assertStatus(200);
        $deleteResponse->assertJson([
            'success' => true,
            'message' => 'Conversation deleted.',
        ]);

        $this->assertSoftDeleted('chat_sessions', [
            'id' => $session->id,
        ]);
    }

    public function test_chat_view_has_unified_sidebar_toggle_and_no_dedicated_close_buttons()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();

        $response = $this->actingAs($user)->get('/chat');
        $response->assertStatus(200);

        // Unified toggle button and icon exist
        $response->assertSee('id="toggleSidebarBtn"', false);
        $response->assertSee('id="toggleSidebarIcon"', false);
        $response->assertSee('id="chatSidebarBackdrop"', false);

        // Dedicated close buttons and redundant offcanvas drawer are removed
        $response->assertDontSee('id="collapseSidebarBtn"', false);
        $response->assertDontSee('id="mobileSessionDrawer"', false);

        // Sidebar is collapsed by default in HTML to prevent animation flashes on page refresh
        $response->assertSee('class="chat-sidebar collapsed"', false);
    }

    public function test_chat_view_places_mic_button_before_submit_button()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Test Session',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}");
        $response->assertStatus(200);

        $html = $response->getContent();
        $micPos = strpos($html, 'id="micBtn"');
        $sendPos = strpos($html, 'id="sendBtn"');

        $this->assertNotFalse($micPos);
        $this->assertNotFalse($sendPos);
        $this->assertLessThan($sendPos, $micPos, 'Mic button must appear before send button.');

        // Verify sendBtn is type="button" to prevent native form submission aborts
        $this->assertStringContainsString('<button type="button" id="sendBtn"', $html);

        // Verify chatForm has safe action and onsubmit preventDefault
        $this->assertStringContainsString('action="javascript:void(0);"', $html);
        $this->assertStringContainsString('onsubmit="event.preventDefault(); return false;"', $html);

        // Verify messageUrl and streamUrl are relative paths (no host/port) to eliminate CORS and origin mismatch
        $this->assertStringContainsString('const messageUrl = "/chat/' . $session->id . '/message";', $html);
        $this->assertStringContainsString('const streamUrl = "/chat/' . $session->id . '/stream";', $html);

        // Verify live thinkingTimer placeholder exists inside typingIndicator
        $this->assertStringContainsString('id="thinkingTimer"', $html);
    }

    public function test_mcp_tool_results_are_hidden_from_user_in_chat_view_only_llm_reply_visible()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'PTO Inquiry',
        ]);

        // 1. User prompt
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'What is my remaining PTO balance?',
        ]);

        // 2. Intermediate assistant message with tool calls (empty content)
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => null,
            'tool_calls' => [
                [
                    'id' => 'call_123',
                    'name' => 'get_employee_leave_balance',
                    'arguments' => ['employee_id' => 'EMP-101'],
                ],
            ],
        ]);

        // 3. Raw MCP tool result
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_TOOL,
            'content' => 'Remaining PTO balance: 14 days vacation, 5 sick days.',
            'tool_call_id' => 'call_123',
        ]);

        // 4. Final LLM synthesized reply
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'You currently have 14 days of remaining vacation time and 5 sick days available.',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}");
        $response->assertStatus(200);

        // User message and synthesized LLM reply MUST be visible
        $response->assertSee('What is my remaining PTO balance?');
        $response->assertSee('You currently have 14 days of remaining vacation time and 5 sick days available.');

        // Raw MCP tool result and MCP system headers MUST NOT be visible
        $response->assertDontSee('MCP System Result');
        $response->assertDontSee('Remaining PTO balance: 14 days vacation, 5 sick days.');
        $response->assertDontSee('class="message-tool"', false);
    }

    public function test_user_can_continue_conversation_with_gemini_provider_after_tool_call()
    {
        // Set Gemini as the default active provider
        LlmProvider::query()->update(['is_default' => false]);
        $gemini = LlmProvider::create([
            'name' => 'Active Gemini 1.5 Flash',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyLiveTestGeminiKey',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'is_default' => true,
            'is_active' => true,
        ]);

        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Gemini Multi-turn',
        ]);

        // Prior turn with tool call
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'Look up company directory for Alice',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => null,
            'tool_calls' => [
                ['id' => 'call_gemini_1', 'name' => 'internal_company_directory', 'arguments' => ['query' => 'Alice']],
            ],
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_TOOL,
            'content' => json_encode(['employee' => 'Alice Smith', 'dept' => 'Design']),
            'tool_call_id' => 'call_gemini_1',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Alice Smith is in the Design department.',
        ]);

        $recordedPayload = null;
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function ($request) use (&$recordedPayload) {
                $recordedPayload = $request->data();

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Alice works on the UX team.'],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                    'usageMetadata' => ['promptTokenCount' => 85, 'candidatesTokenCount' => 20],
                ], 200);
            },
        ]);

        // Follow-up message replying to the LLM's response
        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/message", [
            'message' => 'What sub-team is she on?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('ai_response.content', 'Alice works on the UX team.');

        $this->assertNotNull($recordedPayload);
        $contents = $recordedPayload['contents'];

        // Verify turns alternate correctly: user -> model (functionCall) -> user (functionResponse) -> model (text) -> user (follow-up)
        $this->assertCount(5, $contents);
        $this->assertEquals('user', $contents[0]['role']);
        $this->assertEquals('model', $contents[1]['role']);
        $this->assertEquals('user', $contents[2]['role']);
        $this->assertEquals('model', $contents[3]['role']);
        $this->assertEquals('user', $contents[4]['role']);

        // Verify no bogus 'skip_thought_signature_validator' was injected into the functionCall
        $this->assertArrayNotHasKey('thought_signature', $contents[1]['parts'][0]);

        // Verify functionResponse name matches the tool name and response is an object
        $this->assertEquals('internal_company_directory', $contents[2]['parts'][0]['functionResponse']['name']);
        $this->assertIsObject($contents[2]['parts'][0]['functionResponse']['response']);
    }

    public function test_user_can_stream_chat_response_via_sse()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'SSE Streaming Test',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}/stream?message=Hello+Streaming+World");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $response->assertHeader('Cache-Control', 'no-cache, private');

        // Verify user message was recorded in the database
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Hello Streaming World',
        ]);
    }

    public function test_agent_forces_final_synthesis_when_tool_iteration_limit_is_reached()
    {
        LlmProvider::query()->update(['is_default' => false]);
        $provider = LlmProvider::create([
            'name' => 'Limited Iterations Provider',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyLimitTestingKey',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'max_tool_iterations' => 1, // Only 1 tool iteration allowed
            'is_default' => true,
            'is_active' => true,
        ]);

        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Limit Test Session',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                // Iteration 1: Model requests a tool call
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'functionCall' => [
                                            'name' => 'internal_company_directory',
                                            'args' => ['query' => 'Bob'],
                                        ],
                                    ],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                    'usageMetadata' => ['promptTokenCount' => 30, 'candidatesTokenCount' => 10],
                ], 200)
                // Final synthesis round: Tools are disabled, model synthesizes final answer
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Synthesized Answer: Bob is an Engineering Manager.'],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                    'usageMetadata' => ['promptTokenCount' => 50, 'candidatesTokenCount' => 15],
                ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/message", [
            'message' => 'Find info for Bob',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('ai_response.content', 'Synthesized Answer: Bob is an Engineering Manager.');
        $this->assertNotEquals('Completed', $response->json('ai_response.content'));

        // Verify the synthesized answer was persisted to chat_messages
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Synthesized Answer: Bob is an Engineering Manager.',
        ]);
    }

    public function test_agent_prompts_user_to_wrap_up_or_continue_when_limit_is_reached()
    {
        LlmProvider::query()->update(['is_default' => false]);
        $provider = LlmProvider::create([
            'name' => 'Prompt User Provider',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyLimitTestingKey2',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'max_tool_iterations' => 1,
            'is_default' => true,
            'is_active' => true,
        ]);

        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Limit Prompt Test Session',
        ]);

        $capturedPayloads = [];

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function (\Illuminate\Http\Client\Request $request) use (&$capturedPayloads) {
                $data = $request->data();
                $capturedPayloads[] = $data;

                if (count($capturedPayloads) === 1) {
                    return Http::response([
                        'candidates' => [
                            [
                                'content' => [
                                    'parts' => [
                                        [
                                            'functionCall' => [
                                                'name' => 'internal_company_directory',
                                                'args' => ['query' => 'Alice'],
                                            ],
                                        ],
                                    ],
                                    'role' => 'model',
                                ],
                            ],
                        ],
                        'usageMetadata' => ['promptTokenCount' => 30, 'candidatesTokenCount' => 10],
                    ], 200);
                }

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'I have reached the limit. Should I wrap up or would you like me to continue?'],
                                ],
                                'role' => 'model',
                            ],
                        ],
                    ],
                    'usageMetadata' => ['promptTokenCount' => 50, 'candidatesTokenCount' => 15],
                ], 200);
            },
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/message", [
            'message' => 'Find info for Alice',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ai_response.content', 'I have reached the limit. Should I wrap up or would you like me to continue?');

        // Assert the second request contained the updated prompt notice asking the user
        $this->assertCount(2, $capturedPayloads);
        $systemInstructionText = $capturedPayloads[1]['systemInstruction']['parts'][0]['text'] ?? '';
        $this->assertStringContainsString('Notice: Maximum tool execution limit reached. Please pause and ask the user if they would like you to wrap up the current task, or if they grant you permission to continue running with additional tool calls.', $systemInstructionText);
    }

    public function test_model_router_routes_simple_prompt_to_cheap_model()
    {
        LlmProvider::query()->update(['is_default' => false, 'is_router' => false]);

        $defaultProvider = LlmProvider::create([
            'name' => 'Complex Model Provider',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyComplexKey',
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7,
            'is_default' => true,
            'is_active' => true,
        ]);

        $routerProvider = LlmProvider::create([
            'name' => 'Cheap Router Provider',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyRouterKey',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.1,
            'is_router' => true,
            'is_active' => true,
        ]);

        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Routing Session',
        ]);

        $usedKeys = [];

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function (\Illuminate\Http\Client\Request $request) use (&$usedKeys) {
                // Extract API key from query parameter
                $url = $request->url();
                parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
                $usedKeys[] = $queryParams['key'] ?? 'unknown';

                if (count($usedKeys) === 1) {
                    // First call: Router model classifies prompt as SIMPLE
                    return Http::response([
                        'candidates' => [
                            [
                                'content' => [
                                    'parts' => [['text' => 'SIMPLE']],
                                ],
                                'role' => 'model',
                            ],
                        ],
                        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 2],
                    ], 200);
                }

                // Second call: Generation handled by the cheap router model
                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'Hello! I am the cheap model answering your simple question.']],
                                ],
                                'role' => 'model',
                            ],
                        ],
                        'usageMetadata' => ['promptTokenCount' => 20, 'candidatesTokenCount' => 10],
                    ], 200);
            },
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/message", [
            'message' => 'Hi, what is 2+2?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ai_response.content', 'Hello! I am the cheap model answering your simple question.');

        // Both calls should have hit the router's API key because it was classified as SIMPLE
        $this->assertCount(2, $usedKeys);
        $this->assertEquals('AIzaSyRouterKey', $usedKeys[0]);
        $this->assertEquals('AIzaSyRouterKey', $usedKeys[1]);
    }

    public function test_model_router_routes_complex_prompt_to_default_model()
    {
        LlmProvider::query()->update(['is_default' => false, 'is_router' => false]);

        $defaultProvider = LlmProvider::create([
            'name' => 'Complex Model Provider',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyComplexKey',
            'model' => 'gemini-1.5-pro',
            'temperature' => 0.7,
            'is_default' => true,
            'is_active' => true,
        ]);

        $routerProvider = LlmProvider::create([
            'name' => 'Cheap Router Provider',
            'provider_type' => 'gemini',
            'api_key' => 'AIzaSyRouterKey',
            'model' => 'gemini-1.5-flash',
            'temperature' => 0.1,
            'is_router' => true,
            'is_active' => true,
        ]);

        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Complex Routing Session',
        ]);

        $usedKeys = [];

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function (\Illuminate\Http\Client\Request $request) use (&$usedKeys) {
                $url = $request->url();
                parse_str(parse_url($url, PHP_URL_QUERY), $queryParams);
                $usedKeys[] = $queryParams['key'] ?? 'unknown';

                if (count($usedKeys) === 1) {
                    // First call: Router classifies prompt as COMPLEX
                    return Http::response([
                        'candidates' => [
                            [
                                'content' => [
                                    'parts' => [['text' => 'COMPLEX']],
                                ],
                                'role' => 'model',
                            ],
                        ],
                        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 2],
                    ], 200);
                }

                // Second call: Generation handled by the complex default model
                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [['text' => 'I am the powerful complex model with deep analysis.']],
                                ],
                                'role' => 'model',
                            ],
                        ],
                        'usageMetadata' => ['promptTokenCount' => 50, 'candidatesTokenCount' => 20],
                    ], 200);
            },
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/message", [
            'message' => 'Analyze the financial trends of Q3 with statistical forecasting.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ai_response.content', 'I am the powerful complex model with deep analysis.');

        // First call used the router key; second call used the complex model key
        $this->assertCount(2, $usedKeys);
        $this->assertEquals('AIzaSyRouterKey', $usedKeys[0]);
        $this->assertEquals('AIzaSyComplexKey', $usedKeys[1]);
    }
}
