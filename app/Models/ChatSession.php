<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id', 'asc');
    }

    public function userMessages()
    {
        return $this->hasMany(ChatMessage::class)->where('role', ChatMessage::ROLE_USER);
    }

    public function assistantMessages()
    {
        return $this->hasMany(ChatMessage::class)->where('role', ChatMessage::ROLE_ASSISTANT);
    }

    public function tokenMetrics()
    {
        return $this->hasMany(TokenMetric::class);
    }

    /**
     * Scope to sessions belonging to regular users.
     */
    public function scopeForRegularUsers(Builder $query): Builder
    {
        return $query->whereHas('user', function (Builder $q) {
            $q->where('role', User::ROLE_REGULAR);
        });
    }

    /**
     * Scope to filter sessions by keyword and user.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user_id'] ?? null, function (Builder $q, $userId) {
                $q->where('user_id', $userId);
            })
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhereHas('messages', function (Builder $msgQ) use ($search) {
                            $msgQ->where('content', 'like', "%{$search}%");
                        });
                });
            })
            ->when(isset($filters['status']), function (Builder $q) use ($filters) {
                if ($filters['status'] === 'archived') {
                    $q->onlyTrashed();
                } elseif ($filters['status'] === 'active') {
                    $q->whereNull('deleted_at');
                }
            });
    }
}
