<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\TokenMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserChatAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_regular_user_chat_and_reply_are_recorded_per_session(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'The company vacation policy allows 15 days of PTO.'],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 20,
                    'candidatesTokenCount' => 12,
                    'totalTokenCount' => 32,
                ],
            ], 200),
        ]);

        $regularUser = User::where('role', User::ROLE_REGULAR)->first();

        // 1. Regular user creates a chat session
        $session = ChatSession::create([
            'user_id' => $regularUser->id,
            'title' => 'HR Vacation Question',
        ]);

        // 2. Regular user sends a message
        $response = $this->actingAs($regularUser)->postJson("/chat/{$session->id}/message", [
            'message' => 'How many vacation days do I get per year?',
        ]);

        $response->assertStatus(200);

        // 3. Verify user prompt and assistant reply are both recorded in chat_messages table
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'How many vacation days do I get per year?',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
        ]);

        $assistantMessage = $session->messages()->where('role', ChatMessage::ROLE_ASSISTANT)->first();
        $this->assertNotNull($assistantMessage);
        $this->assertNotEmpty($assistantMessage->content);

        // 4. Verify session message count
        $this->assertEquals(2, $session->messages()->count());
    }

    public function test_guest_cannot_access_admin_user_chats(): void
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create(['user_id' => $regularUser->id, 'title' => 'Confidential Chat']);

        $this->get('/admin/user-chats')->assertRedirect('/login');
        $this->get("/admin/user-chats/{$session->id}")->assertRedirect('/login');
        $this->get("/admin/user-chats/{$session->id}/export")->assertRedirect('/login');
        $this->delete("/admin/user-chats/{$session->id}")->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_admin_user_chats(): void
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create(['user_id' => $regularUser->id, 'title' => 'Secret Chat']);

        // Regular user attempts to access admin audit logs
        $this->actingAs($regularUser)->get('/admin/user-chats')->assertStatus(403);
        $this->actingAs($regularUser)->get("/admin/user-chats/{$session->id}")->assertStatus(403);
        $this->actingAs($regularUser)->get("/admin/user-chats/{$session->id}/export")->assertStatus(403);
        $this->actingAs($regularUser)->delete("/admin/user-chats/{$session->id}")->assertStatus(403);
    }

    public function test_admin_can_view_all_regular_user_chat_sessions(): void
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $session = ChatSession::create([
            'user_id' => $regularUser->id,
            'title' => 'Employee Benefits Inquiries',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'What is the health insurance policy?',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Full dental, vision, and health are provided.',
        ]);

        $response = $this->actingAs($admin)->get('/admin/user-chats');

        $response->assertStatus(200);
        $response->assertSee('Employee Benefits Inquiries');
        $response->assertSee($regularUser->name);
        $response->assertSee($regularUser->email);
        $response->assertSee('Recorded Conversations');
    }

    public function test_admin_can_view_regular_user_chat_transcript(): void
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $session = ChatSession::create([
            'user_id' => $regularUser->id,
            'title' => 'Tax Forms Assistance',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'Where do I find my W2 form?',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'W2 forms can be downloaded from ADP under Tax Statements.',
            'feedback' => 'like',
        ]);

        TokenMetric::create([
            'user_id' => $regularUser->id,
            'chat_session_id' => $session->id,
            'provider' => 'Google Gemini',
            'model' => 'gemini-1.5-flash',
            'prompt_tokens' => 25,
            'completion_tokens' => 15,
            'total_tokens' => 40,
            'estimated_cost' => 0.0001,
        ]);

        $response = $this->actingAs($admin)->get("/admin/user-chats/{$session->id}");

        $response->assertStatus(200);
        $response->assertSee('Tax Forms Assistance');
        $response->assertSee('Where do I find my W2 form?');
        $response->assertSee('W2 forms can be downloaded from ADP under Tax Statements.');
        $response->assertSee('Liked');
        $response->assertSee('gemini-1.5-flash');
    }

    public function test_when_regular_user_deletes_session_it_is_preserved_for_admin_audit(): void
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $session = ChatSession::create([
            'user_id' => $regularUser->id,
            'title' => 'Deleted Sensitive Conversation',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => 'I plan to delete this conversation immediately.',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Understood, but all records are safely audited by administrators.',
        ]);

        // Regular user deletes the conversation
        $deleteResponse = $this->actingAs($regularUser)->delete("/chat/{$session->id}");
        $deleteResponse->assertRedirect('/chat');

        // Regular user no longer sees the session in active chat
        $userChatResponse = $this->actingAs($regularUser)->get('/chat');
        $userChatResponse->assertDontSee('Deleted Sensitive Conversation');

        // But database retains the session (soft deleted) and messages
        $this->assertSoftDeleted('chat_sessions', ['id' => $session->id]);
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'content' => 'I plan to delete this conversation immediately.',
        ]);

        // Admin can still view it in the audit logs
        $adminListResponse = $this->actingAs($admin)->get('/admin/user-chats');
        $adminListResponse->assertStatus(200);
        $adminListResponse->assertSee('Deleted Sensitive Conversation');
        $adminListResponse->assertSee('Deleted by user');

        // Admin can view the full transcript
        $adminDetailResponse = $this->actingAs($admin)->get("/admin/user-chats/{$session->id}");
        $adminDetailResponse->assertStatus(200);
        $adminDetailResponse->assertSee('I plan to delete this conversation immediately.');
        $adminDetailResponse->assertSee('Deleted by user');
    }

    public function test_admin_can_filter_and_search_user_chats(): void
    {
        $regularUser1 = User::where('role', User::ROLE_REGULAR)->first();
        $regularUser2 = User::factory()->create([
            'name' => 'Bob Smith',
            'email' => 'bob@company.com',
            'role' => User::ROLE_REGULAR,
        ]);
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $session1 = ChatSession::create([
            'user_id' => $regularUser1->id,
            'title' => 'Budget Report Alpha',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session1->id,
            'role' => 'user',
            'content' => 'Quarterly earnings query',
        ]);

        $session2 = ChatSession::create([
            'user_id' => $regularUser2->id,
            'title' => 'Server Maintenance Schedule',
        ]);
        ChatMessage::create([
            'chat_session_id' => $session2->id,
            'role' => 'user',
            'content' => 'PostgreSQL upgrade downtime',
        ]);

        // Filter by user_id
        $filterUser = $this->actingAs($admin)->get("/admin/user-chats?user_id={$regularUser2->id}");
        $filterUser->assertSee('Server Maintenance Schedule');
        $filterUser->assertDontSee('Budget Report Alpha');

        // Search by keyword in message
        $searchResponse = $this->actingAs($admin)->get('/admin/user-chats?search=PostgreSQL');
        $searchResponse->assertSee('Server Maintenance Schedule');
        $searchResponse->assertDontSee('Budget Report Alpha');
    }

    public function test_admin_can_export_transcript(): void
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $session = ChatSession::create([
            'user_id' => $regularUser->id,
            'title' => 'Exportable Audit Trail',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Export prompt content',
        ]);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Export reply content',
        ]);

        // JSON export
        $jsonResponse = $this->actingAs($admin)->get("/admin/user-chats/{$session->id}/export?format=json");
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertJsonFragment(['content' => 'Export prompt content']);
        $jsonResponse->assertJsonFragment(['content' => 'Export reply content']);

        // TXT export
        $txtResponse = $this->actingAs($admin)->get("/admin/user-chats/{$session->id}/export?format=txt");
        $txtResponse->assertStatus(200);
        $this->assertStringContainsString('Export prompt content', $txtResponse->getContent());
        $this->assertStringContainsString('Export reply content', $txtResponse->getContent());
    }

    public function test_admin_can_permanently_purge_chat_session(): void
    {
        $regularUser = User::where('role', User::ROLE_REGULAR)->first();
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $session = ChatSession::create([
            'user_id' => $regularUser->id,
            'title' => 'Purge Me Session',
        ]);

        $response = $this->actingAs($admin)->delete("/admin/user-chats/{$session->id}");
        $response->assertRedirect('/admin/user-chats');

        $this->assertDatabaseMissing('chat_sessions', ['id' => $session->id]);
    }
}
