<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    public const ROLE_TOOL = 'tool';

    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'tool_calls',
        'tool_call_id',
        'feedback',
    ];

    protected $casts = [
        'tool_calls' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
