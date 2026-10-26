<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chat_session_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class);
    }
}
