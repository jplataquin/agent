<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\LlmProvider;
use App\Services\AiAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->get();

        $activeSession = $sessions->first();
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

        $response = $this->aiService->generateResponse($session);

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

        $response = $this->aiService->generateResponse($session);

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

        $response = $this->aiService->generateResponse($session);

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

        return new StreamedResponse(function () use ($session) {
            // Disable output buffering
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $onChunk = function ($text) {
                echo 'data: '.json_encode(['chunk' => $text])."\n\n";
                flush();
            };

            $result = $this->aiService->generateResponse($session, $onChunk);

            echo 'data: '.json_encode(['done' => true, 'result' => $result])."\n\n";
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
