<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\LlmProvider;
use App\Services\AiAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        protected AiAgentService $aiService
    ) {
        $this->middleware('auth');
    }

    /**
     * Show main chat interface.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $sessions = $user->chatSessions()
            ->where(function ($q) {
                $q->where('updated_at', '>=', now()->subHours(12))
                  ->orWhere('is_pinned', true);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        $activeSession = $sessions->first();

        // 5-minute inactivity rule: Auto-create a new session if the most recent is too old
        // Or if there are no sessions at all. 
        // We ensure the old session actually has messages to avoid creating infinite empty sessions on refresh.
        if (! $activeSession || ($activeSession->messages()->count() > 0 && $activeSession->updated_at < now()->subMinutes(5))) {
            $activeSession = ChatSession::create([
                'user_id' => $user->id,
                'title' => 'New Chat',
            ]);
            
            // Re-fetch to ensure correct ordering (e.g. if there are pinned sessions)
            $sessions = $user->chatSessions()
                ->where(function ($q) {
                    $q->where('updated_at', '>=', now()->subHours(12))
                      ->orWhere('is_pinned', true);
                })
                ->orderByDesc('is_pinned')
                ->orderByDesc('updated_at')
                ->get();
        }

        $activeProvider = LlmProvider::getDefault();

        return view('chat.index', compact('sessions', 'activeSession', 'activeProvider'));
    }

    /**
     * Show a specific chat session.
     */
    public function show(ChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        $sessions = Auth::user()->chatSessions()
            ->where(function ($q) {
                $q->where('updated_at', '>=', now()->subHours(12))
                  ->orWhere('is_pinned', true);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        $activeSession = $session;
        $activeProvider = LlmProvider::getDefault();

        return view('chat.index', compact('sessions', 'activeSession', 'activeProvider'));
    }

    /**
     * Create a new chat session.
     */
    public function store(Request $request)
    {
        $session = ChatSession::create([
            'user_id' => Auth::id(),
            'title' => 'New Chat',
        ]);

        return redirect()->route('chat.show', $session);
    }

    /**
     * Delete a chat session.
     */
    public function destroy(Request $request, ChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        $session->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted.',
            ]);
        }

        return redirect()->route('chat.index')->with('success', 'Conversation deleted.');
    }

    /**
     * Rename a chat session.
     */
    public function rename(Request $request, ChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string|max:100',
        ]);

        $session->update(['title' => $request->input('title')]);

        return response()->json([
            'success' => true,
            'title' => $session->title,
        ]);
    }

    /**
     * Toggle pinned state for a chat session.
     */
    public function togglePin(ChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        $session->update(['is_pinned' => ! $session->is_pinned]);

        return response()->json([
            'success' => true,
            'is_pinned' => $session->is_pinned,
        ]);
    }

    /**
     * Submit feedback (like / dislike) for an assistant message.
     */
    public function feedback(Request $request, ChatSession $session, ChatMessage $message)
    {
        if ($session->user_id !== Auth::id() || $message->chat_session_id !== $session->id) {
            abort(403);
        }

        $request->validate([
            'feedback' => 'nullable|in:like,dislike',
        ]);

        $message->update(['feedback' => $request->input('feedback')]);

        return response()->json([
            'success' => true,
            'feedback' => $message->feedback,
        ]);
    }

    /**
     * Extract client device context (is_mobile and screen_width).
     */
    protected function extractClientContext(Request $request): array
    {
        $isMobileParam = $request->has('is_mobile') ? $request->boolean('is_mobile') : null;
        $screenWidth = $request->filled('screen_width') ? (int) $request->input('screen_width') : null;

        $isMobile = $isMobileParam ?? (
            $request->header('sec-ch-ua-mobile') === '?1'
            || ($screenWidth !== null && $screenWidth <= 768)
            || (bool) preg_match('/Mobile|Android|iPhone|iPod/i', $request->userAgent() ?? '')
        );

        return [
            'is_mobile' => $isMobile,
            'screen_width' => $screenWidth,
        ];
    }

    /**
     * Regenerate the latest assistant response.
     */
    public function regenerate(Request $request, ChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        // Delete trailing assistant or tool messages after the last user message
        $lastUserMsg = $session->messages()->where('role', ChatMessage::ROLE_USER)->latest('id')->first();

        if (! $lastUserMsg) {
            return response()->json(['error' => 'No user message to regenerate from.'], 400);
        }

        $session->messages()->where('id', '>', $lastUserMsg->id)->delete();

        try {
            $response = $this->aiService->generateResponse($session, null, null, $this->extractClientContext($request));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ChatController regenerate error: '.$e->getMessage(), ['exception' => $e]);
            $response = [
                'error' => $e->getMessage(),
                'content' => '⚠️ Error regenerating response: '.$e->getMessage(),
            ];
        }

        return response()->json([
            'success' => true,
            'ai_response' => $response,
        ]);
    }

    /**
     * Edit a sent user prompt, remove subsequent turns, and re-generate response.
     */
    public function editMessage(Request $request, ChatSession $session, ChatMessage $message)
    {
        if ($session->user_id !== Auth::id() || $message->chat_session_id !== $session->id) {
            abort(403);
        }

        $request->validate([
            'content' => 'required|string',
        ]);

        $message->update(['content' => $request->input('content')]);

        // Remove all subsequent messages
        $session->messages()->where('id', '>', $message->id)->delete();

        try {
            $response = $this->aiService->generateResponse($session, null, null, $this->extractClientContext($request));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ChatController editMessage error: '.$e->getMessage(), ['exception' => $e]);
            $response = [
                'error' => $e->getMessage(),
                'content' => '⚠️ Error generating response: '.$e->getMessage(),
            ];
        }

        return response()->json([
            'success' => true,
            'ai_response' => $response,
        ]);
    }

    /**
     * Handle user posting message and returning AI response via JSON / SSE.
     */
    public function sendMessage(Request $request, ChatSession $session)
    {
        @set_time_limit(120);

        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = $session->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => $request->input('message'),
        ]);

        // Auto-title session based on first user message
        if ($session->messages()->count() <= 2) {
            $title = substr($userMessage->content, 0, 35);
            if (strlen($userMessage->content) > 35) {
                $title .= '...';
            }
            $session->update(['title' => $title]);
        }

        try {
            $response = $this->aiService->generateResponse($session, null, null, $this->extractClientContext($request));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ChatController sendMessage error: '.$e->getMessage(), ['exception' => $e]);
            $response = [
                'error' => $e->getMessage(),
                'content' => '⚠️ Error generating response: '.$e->getMessage(),
            ];
        }

        return response()->json([
            'success' => true,
            'user_message' => $userMessage,
            'ai_response' => $response,
            'session_title' => $session->title,
        ]);
    }

    /**
     * Real-time Server-Sent Events (SSE) streaming endpoint.
     */
    public function stream(Request $request, ChatSession $session)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        $userPrompt = $request->query('message');
        if (! empty($userPrompt)) {
            $userMsg = $session->messages()->create([
                'role' => ChatMessage::ROLE_USER,
                'content' => $userPrompt,
            ]);

            if ($session->messages()->count() <= 2) {
                $session->update([
                    'title' => substr($userPrompt, 0, 35).(strlen($userPrompt) > 35 ? '...' : ''),
                ]);
            }
        }

        $clientContext = $this->extractClientContext($request);

        return new StreamedResponse(function () use ($session, $clientContext) {
            $isTesting = app()->runningUnitTests() || app()->environment('testing') || defined('PHPUNIT_RUNNING');

            // Disable all levels of output buffering and compression only when not in unit tests
            if (! $isTesting) {
                @set_time_limit(300);
                @ini_set('max_execution_time', '300');
                @ignore_user_abort(false);
                @ini_set('zlib.output_compression', '0');
                @ini_set('implicit_flush', '1');
                if (function_exists('apache_setenv')) {
                    @apache_setenv('no-gzip', '1');
                }
                ob_implicit_flush(true);
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
            }

            $flushOutput = function () use ($isTesting) {
                if (! $isTesting) {
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();
                }
            };

            // Send 2KB comment padding to immediately punch through reverse proxy and firewall buffers
            echo ': '.str_repeat(' ', 2048)."\n\n";
            echo ": connected\n\n";
            $flushOutput();

            $onHeartbeat = function () use ($flushOutput, $session) {
                if (Cache::get('chat_cancel_'.$session->id)) {
                    throw new \RuntimeException('Response generation cancelled by user.');
                }
                echo ": keepalive\n\n";
                echo 'data: '.json_encode(['keepalive' => true])."\n\n";
                $flushOutput();
            };

            $onChunk = function ($text) use ($flushOutput) {
                echo 'data: '.json_encode(['chunk' => $text])."\n\n";
                $flushOutput();
            };

            try {
                $result = $this->aiService->generateResponse($session, $onChunk, $onHeartbeat, $clientContext);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('ChatController stream error: '.$e->getMessage(), ['exception' => $e]);
                $result = [
                    'error' => $e->getMessage(),
                    'content' => '⚠️ Error generating response: '.$e->getMessage(),
                ];
                $onChunk($result['content']);
            }

            echo 'data: '.json_encode([
                'done' => true,
                'result' => $result,
                'session_title' => $session->title,
            ])."\n\n";
            $flushOutput();
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, private',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Request an early synthesis of the active streaming response.
     */
    public function wrapUp(Request $request, ChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        Cache::put('chat_wrap_up_'.$session->id, true, now()->addMinutes(10));

        return response()->json([
            'status' => 'success',
            'message' => 'Wrap up signal sent successfully.',
        ]);
    }

    /**
     * Cancel the active streaming response and any ongoing LLM/tool actions.
     */
    public function cancel(Request $request, ChatSession $session)
    {
        if ($session->user_id !== Auth::id()) {
            abort(403);
        }

        Cache::put('chat_cancel_'.$session->id, true, now()->addMinutes(5));

        return response()->json([
            'status' => 'success',
            'message' => 'Action cancelled successfully.',
        ]);
    }
}
