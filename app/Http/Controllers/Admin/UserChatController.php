<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\TokenMetric;
use App\Models\User;
use Illuminate\Http\Request;

class UserChatController extends Controller
{
    /**
     * Display a listing of regular user chat sessions with filters.
     */
    public function index(Request $request)
    {
        $roleFilter = $request->input('role', 'all');

        $query = ChatSession::withTrashed()
            ->with(['user'])
            ->withCount([
                'messages',
                'userMessages',
                'assistantMessages',
            ]);

        // Filter by role (default: all users)
        if ($roleFilter !== 'all') {
            $query->whereHas('user', function ($q) use ($roleFilter) {
                $q->where('role', $roleFilter);
            });
        }

        // Filter by user ID
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by search keyword in title or message content
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($sub) use ($search) {
                $sub->where('title', 'like', "%{$search}%")
                    ->orWhereHas('messages', function ($msgQ) use ($search) {
                        $msgQ->where('content', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status (active / archived/deleted by user)
        if ($request->filled('status')) {
            if ($request->input('status') === 'archived') {
                $query->onlyTrashed();
            } elseif ($request->input('status') === 'active') {
                $query->whereNull('deleted_at');
            }
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $sessions = $query->latest('updated_at')->paginate(15)->withQueryString();

        // Calculate summary KPI stats based on active role filter
        $targetUserIds = match ($roleFilter) {
            'regular' => User::where('role', User::ROLE_REGULAR)->pluck('id'),
            'admin' => User::where('role', User::ROLE_ADMIN)->pluck('id'),
            default => User::pluck('id'),
        };
        $kpiSessionsQuery = ChatSession::withTrashed()->whereIn('user_id', $targetUserIds);

        $kpi = [
            'total_sessions' => (clone $kpiSessionsQuery)->count(),
            'total_user_prompts' => ChatMessage::whereHas('session', function ($q) use ($targetUserIds) {
                $q->withTrashed()->whereIn('user_id', $targetUserIds);
            })->where('role', ChatMessage::ROLE_USER)->count(),
            'total_assistant_replies' => ChatMessage::whereHas('session', function ($q) use ($targetUserIds) {
                $q->withTrashed()->whereIn('user_id', $targetUserIds);
            })->where('role', ChatMessage::ROLE_ASSISTANT)->count(),
            'total_regular_tokens' => TokenMetric::whereIn('user_id', $targetUserIds)->sum('total_tokens'),
            'total_regular_cost' => TokenMetric::whereIn('user_id', $targetUserIds)->sum('estimated_cost'),
            'active_chatters_count' => (clone $kpiSessionsQuery)->distinct('user_id')->count('user_id'),
        ];

        $users = User::orderBy('name')->get();

        return view('admin.user_chats.index', compact('sessions', 'kpi', 'users', 'roleFilter'));
    }

    /**
     * Display a specific chat session with its full transcript of user prompts and AI replies.
     */
    public function show($id)
    {
        $session = ChatSession::withTrashed()
            ->with(['user', 'messages', 'tokenMetrics'])
            ->withCount(['userMessages', 'assistantMessages'])
            ->findOrFail($id);

        $tokenStats = [
            'total_tokens' => $session->tokenMetrics->sum('total_tokens'),
            'prompt_tokens' => $session->tokenMetrics->sum('prompt_tokens'),
            'completion_tokens' => $session->tokenMetrics->sum('completion_tokens'),
            'estimated_cost' => $session->tokenMetrics->sum('estimated_cost'),
            'models' => $session->tokenMetrics->pluck('model')->unique()->filter()->values(),
        ];

        return view('admin.user_chats.show', compact('session', 'tokenStats'));
    }

    /**
     * Export the conversation transcript as JSON or text format.
     */
    public function export(Request $request, $id)
    {
        $session = ChatSession::withTrashed()
            ->with(['user', 'messages'])
            ->findOrFail($id);

        $format = $request->query('format', 'json');

        if ($format === 'txt') {
            $text = "Conversation Transcript: {$session->title}\n";
            $text .= "User: {$session->user->name} ({$session->user->email})\n";
            $text .= "Session ID: {$session->id}\n";
            $text .= "Started At: {$session->created_at->toIso8601String()}\n";
            $text .= 'Status: '.($session->trashed() ? 'Deleted by user on '.$session->deleted_at->toIso8601String() : 'Active')."\n";
            $text .= str_repeat('=', 60)."\n\n";

            foreach ($session->messages as $msg) {
                $roleLabel = strtoupper($msg->role);
                $time = $msg->created_at ? $msg->created_at->format('Y-m-d H:i:s') : 'N/A';
                $text .= "[{$time}] {$roleLabel}:\n";
                $text .= ($msg->content ?? '')."\n\n";
            }

            $fileName = 'chat_session_'.$session->id.'_'.now()->format('Ymd_His').'.txt';

            return response($text, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }

        $payload = [
            'session_id' => $session->id,
            'title' => $session->title,
            'user' => [
                'id' => $session->user->id,
                'name' => $session->user->name,
                'email' => $session->user->email,
                'role' => $session->user->role,
            ],
            'created_at' => $session->created_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
            'deleted_at' => $session->deleted_at?->toIso8601String(),
            'is_deleted_by_user' => $session->trashed(),
            'messages_count' => $session->messages->count(),
            'messages' => $session->messages->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'role' => $msg->role,
                    'content' => $msg->content,
                    'tool_calls' => $msg->tool_calls,
                    'tool_call_id' => $msg->tool_call_id,
                    'feedback' => $msg->feedback,
                    'created_at' => $msg->created_at?->toIso8601String(),
                ];
            }),
        ];

        $fileName = 'chat_session_'.$session->id.'_'.now()->format('Ymd_His').'.json';

        return response()->json($payload, 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Permanently remove a chat session and its recorded messages (Admin only).
     */
    public function destroy($id)
    {
        $session = ChatSession::withTrashed()->findOrFail($id);
        $session->forceDelete();

        return redirect()->route('admin.user-chats.index')
            ->with('success', 'Conversation record has been permanently deleted.');
    }
}
