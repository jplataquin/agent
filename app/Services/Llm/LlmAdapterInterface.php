<?php

namespace App\Services\Llm;

use App\Models\LlmProvider;

interface LlmAdapterInterface
{
    /**
     * Send chat request and return normalized response.
     *
     * @param  array<int, array{role: string, content: ?string, tool_calls?: ?array, tool_call_id?: ?string}>  $messages
     * @param  array<int, array{type: string, function: array, server_id: int}>  $mcpTools
     */
    public function send(LlmProvider $provider, string $systemPrompt, array $messages, array $mcpTools): LlmResponse;
}
