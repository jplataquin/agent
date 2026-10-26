<?php

namespace App\Services\Llm;

class LlmResponse
{
    /**
     * @param  array<int, array{id: string, name: string, arguments: array}>  $toolCalls
     */
    public function __construct(
        public ?string $content = null,
        public array $toolCalls = [],
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public ?string $errorMessage = null,
    ) {}

    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    public function isError(): bool
    {
        return $this->errorMessage !== null;
    }
}
