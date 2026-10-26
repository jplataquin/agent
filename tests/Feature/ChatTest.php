<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
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
}
