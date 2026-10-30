<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAdvancedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['view.paths' => [dirname(__DIR__, 2) . '/resources/views']]);
        app()->forgetInstance('view.finder');
        \Illuminate\Support\Facades\View::clearResolvedInstances();
        $this->seed();
    }

    public function test_user_can_rename_conversation()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Original Title',
        ]);

        $response = $this->actingAs($user)->patchJson("/chat/{$session->id}/rename", [
            'title' => 'Updated Project Discussion',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('title', 'Updated Project Discussion');
        $this->assertDatabaseHas('chat_sessions', [
            'id' => $session->id,
            'title' => 'Updated Project Discussion',
        ]);
    }

    public function test_user_can_toggle_conversation_pin()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Important Meeting Notes',
            'is_pinned' => false,
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/pin");
        $response->assertStatus(200);
        $response->assertJsonPath('is_pinned', true);
        $this->assertTrue($session->fresh()->is_pinned);

        // Toggle back
        $response2 = $this->actingAs($user)->postJson("/chat/{$session->id}/pin");
        $response2->assertStatus(200);
        $response2->assertJsonPath('is_pinned', false);
        $this->assertFalse($session->fresh()->is_pinned);
    }

    public function test_user_can_submit_message_feedback()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create(['user_id' => $user->id]);
        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Here is the requested information.',
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/messages/{$message->id}/feedback", [
            'feedback' => 'like',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('feedback', 'like');
        $this->assertEquals('like', $message->fresh()->feedback);
    }

    public function test_user_can_regenerate_assistant_response()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create(['user_id' => $user->id]);

        $userMsg = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'First prompt',
        ]);

        $assistantMsg = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Initial assistant response',
        ]);

        $response = $this->actingAs($user)->postJson("/chat/{$session->id}/regenerate");
        $response->assertStatus(200);

        // Previous assistant message should have been replaced
        $this->assertDatabaseMissing('chat_messages', [
            'id' => $assistantMsg->id,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'assistant',
        ]);
    }

    public function test_user_can_edit_sent_prompt_and_rebranch()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create(['user_id' => $user->id]);

        $userMsg = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Old prompt with typos',
        ]);

        $assistantMsg = ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Response to old prompt',
        ]);

        $response = $this->actingAs($user)->putJson("/chat/{$session->id}/messages/{$userMsg->id}", [
            'content' => 'Corrected prompt without typos',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Corrected prompt without typos', $userMsg->fresh()->content);
        $this->assertDatabaseMissing('chat_messages', [
            'id' => $assistantMsg->id,
        ]);
    }

    public function test_chat_view_renders_auto_tts_toggle_and_controls()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Test TTS Chat',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}");
        $response->assertStatus(200);
        $response->assertSee('id="autoTtsToggle"', false);
        $response->assertSee('Auto-read', false);
        $response->assertSee('chat_auto_read_aloud', false);
    }

    public function test_chat_view_renders_mermaid_library_and_renderer()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Test Mermaid Chat',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}");
        $response->assertStatus(200);
        $response->assertSee('mermaid@10/dist/mermaid.min.js', false);
        $response->assertSee('renderMermaidGraphs', false);
    }

    public function test_chat_view_renders_latex_math_libraries_and_renderer()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Test LaTeX Chat',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}");
        $response->assertStatus(200);
        $response->assertSee('katex@0.16.11/dist/katex.min.css', false);
        $response->assertSee('katex@0.16.11/dist/katex.min.js', false);
        $response->assertSee('katex@0.16.11/dist/contrib/auto-render.min.js', false);
        $response->assertSee('mathExtension', false);
        $response->assertSee('window.renderMath', false);
    }

    public function test_chat_view_renders_assistant_latex_formulas()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Math Formula Chat',
        ]);

        $latexContent = 'The formula is $E = mc^2$ and block: $$\\int_0^1 x^2 dx = \\frac{1}{3}$$';
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $latexContent,
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}");
        $response->assertStatus(200);
        $response->assertSee('message-assistant markdown-body', false);
        $response->assertSee(e($latexContent), false);
    }

    public function test_chat_view_renders_prompt_edit_and_send_handling()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Prompt Edit Chat',
        ]);

        $response = $this->actingAs($user)->get("/chat/{$session->id}");
        $response->assertStatus(200);
        $response->assertSee('window.startEditUserPrompt', false);
        $response->assertSee('window.cancelEditUserPrompt', false);
        $response->assertSee('window.submitEditUserPrompt', false);
        $response->assertSee('window.submitMessage = submitMessage;', false);
        $response->assertSee('window.scrollToBottom = scrollToBottom;', false);
    }
}
