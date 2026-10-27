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
}
