<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlmProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider_type',
        'api_key',
        'model',
        'base_url',
        'temperature',
        'max_tokens',
        'max_tool_iterations',
        'wrap_up_timeout',
        'is_default',
        'is_router',
        'is_active',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'max_tool_iterations' => 'integer',
        'wrap_up_timeout' => 'integer',
        'is_default' => 'boolean',
        'is_router' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function getDefault(): ?self
    {
        return self::where('is_default', true)->where('is_active', true)->first()
            ?? self::where('is_active', true)->first();
    }

    public static function getRouter(): ?self
    {
        return self::where('is_router', true)->where('is_active', true)->first();
    }

    public function logs()
    {
        return $this->hasMany(LlmLog::class, 'llm_provider_id')->orderByDesc('created_at');
    }
}
