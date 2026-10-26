<?php

namespace App\Services\Llm;

use App\Models\LlmProvider;

class LlmAdapterFactory
{
    public function make(LlmProvider $provider): LlmAdapterInterface
    {
        return match ($provider->provider_type) {
            'anthropic' => app(AnthropicAdapter::class),
            'gemini' => app(GeminiAdapter::class),
            'openai', 'openrouter', 'custom' => app(OpenAiAdapter::class),
            default => app(OpenAiAdapter::class),
        };
    }
}
