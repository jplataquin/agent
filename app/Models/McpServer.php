<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class McpServer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'endpoint_url',
        'auth_type',
        'auth_token',
        'custom_headers',
        'description',
        'tools_cache',
        'is_active',
    ];

    protected $casts = [
        'custom_headers' => 'array',
        'tools_cache' => 'array',
        'is_active' => 'boolean',
    ];
}
