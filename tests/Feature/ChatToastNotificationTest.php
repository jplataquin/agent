<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatToastNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_chat_page_includes_toast_notification_container_and_styles()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();

        $response = $this->actingAs($user)->get('/chat');
        $response->assertStatus(200);

        // Verify toast container exists
        $response->assertSee('id="chatToastContainer"', false);
        $response->assertSee('class="chat-toast-container toast-container position-absolute"', false);

        // Verify position absolute and high z-index in stylesheet
        $response->assertSee('.chat-toast-container {', false);
        $response->assertSee('position: absolute !important;', false);
        $response->assertSee('z-index: 9999 !important;', false);
        $response->assertSee('top: 16px;', false);

        // Verify toast notification styles
        $response->assertSee('.chat-toast,', false);
        $response->assertSee('.toast-notification {', false);

        // Verify JavaScript toast management functions are present
        $response->assertSee('window.showToast = function', false);
        $response->assertSee('window.dismissToast = function', false);
        $response->assertSee('toastSlideDown', false);
    }

    public function test_chat_page_does_not_render_in_flow_layout_alert_container()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();

        // Visit chat with a session flash message
        $response = $this->actingAs($user)
            ->withSession(['success' => 'Test success message'])
            ->get('/chat');

        $response->assertStatus(200);

        // Verify the message is rendered as a chat toast notification
        $response->assertSee('chat-toast toast-notification toast-success', false);
        $response->assertSee('Test success message');
        $response->assertSee('toast-close-btn btn-close', false);

        // Verify it is NOT rendered as the layout's in-flow container alert that pushes the page
        $html = $response->getContent();
        $this->assertStringNotContainsString(
            '<div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">',
            $html,
            'In-flow layout alert must not be rendered on /chat.'
        );
    }

    public function test_chat_page_renders_error_toast_with_close_button()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();

        $response = $this->actingAs($user)
            ->withSession(['error' => 'An unexpected failure occurred'])
            ->get('/chat');

        $response->assertStatus(200);

        // Verify error toast renders with toast-danger class, high z-index container, and close button
        $response->assertSee('chat-toast toast-notification toast-danger', false);
        $response->assertSee('An unexpected failure occurred');
        $response->assertSee('aria-label="Close"', false);
        $response->assertSee('data-autohide="true"', false);
    }

    public function test_deleting_conversation_redirects_and_displays_toast_notification()
    {
        $user = User::where('role', User::ROLE_REGULAR)->first();
        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'To be deleted',
        ]);

        $response = $this->actingAs($user)->delete("/chat/{$session->id}");
        $response->assertRedirect('/chat');
        $response->assertSessionHas('success', 'Conversation deleted.');

        $followResponse = $this->actingAs($user)
            ->withSession(['success' => 'Conversation deleted.'])
            ->get('/chat');
        $followResponse->assertStatus(200);

        // Confirm session toast container renders the deleted notification
        $followResponse->assertSee('id="chatToastContainer"', false);
        $followResponse->assertSee('Conversation deleted.');
        $followResponse->assertSee('toast-success', false);
        $followResponse->assertSee('position: absolute !important;', false);
        $followResponse->assertSee('z-index: 9999 !important;', false);
    }

    public function test_non_chat_pages_still_render_regular_layout_alerts()
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        // Non-chat page (e.g. admin dashboard or metrics)
        $response = $this->actingAs($admin)
            ->withSession(['success' => 'Admin operation completed'])
            ->get('/admin/metrics');

        $response->assertStatus(200);
        $response->assertSee('alert alert-success alert-dismissible', false);
        $response->assertSee('Admin operation completed');
    }

    public function test_no_bg_light_class_in_blade_views_and_dark_mode_override_defined()
    {
        // 1. Verify layouts/app.blade.php contains dark mode overrides for bg-light
        $layoutContent = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('[data-bs-theme="dark"] .bg-light', $layoutContent);

        // 2. Scan all blade views (excluding the dark theme CSS rule in app.blade.php itself)
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                if ($file->getFilename() === 'app.blade.php') {
                    continue;
                }
                $content = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression(
                    '/\bclass=["\'][^"\']*\bbg-light\b/',
                    $content,
                    "File {$file->getPathname()} should not contain bg-light class for dark mode theme compatibility."
                );
            }
        }
    }
}
