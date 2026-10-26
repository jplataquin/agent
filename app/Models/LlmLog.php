<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'llm_provider_id',
        'chat_session_id',
        'user_id',
        'action',
        'endpoint',
        'http_method',
        'status_code',
        'latency_ms',
        'request_payload',
        'response_body',
        'error_message',
        'prompt_tokens',
        'completion_tokens',
        'is_success',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'status_code' => 'integer',
        'latency_ms' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'is_success' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'llm_provider_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Safely record an LLM call while automatically masking sensitive API keys.
     */
    public static function record(
        LlmProvider $provider,
        string $endpoint,
        string $httpMethod,
        ?array $requestPayload,
        ?int $statusCode,
        ?string $responseBody,
        int $latencyMs,
        bool $isSuccess,
        ?string $errorMessage = null,
        int $promptTokens = 0,
        int $completionTokens = 0,
        string $action = 'chat_completion',
        ?int $sessionId = null,
        ?int $userId = null
    ): self {
        // Mask API keys in endpoint
        $maskedEndpoint = preg_replace('/(key=)([^&\s]+)/i', '$1••••••••', $endpoint);
        $maskedEndpoint = preg_replace('/(bearer\s+)([^&\s]+)/i', '$1••••••••', (string) $maskedEndpoint);

        return self::create([
            'llm_provider_id' => $provider->id,
            'chat_session_id' => $sessionId,
            'user_id' => $userId,
            'action' => $action,
            'endpoint' => $maskedEndpoint,
            'http_method' => strtoupper($httpMethod),
            'status_code' => $statusCode,
            'latency_ms' => $latencyMs,
            'request_payload' => $requestPayload,
            'response_body' => $responseBody ? substr($responseBody, 0, 50000) : null,
            'error_message' => $errorMessage,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'is_success' => $isSuccess,
        ]);
    }
}
