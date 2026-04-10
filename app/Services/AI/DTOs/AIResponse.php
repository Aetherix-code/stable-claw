<?php

namespace App\Services\AI\DTOs;

readonly class AIResponse
{
    /**
     * @param  AIToolCall[]  $toolCalls
     * @param  array{input_tokens?: int, output_tokens?: int}|null  $usage
     */
    public function __construct(
        public ?string $content,
        public string $finishReason, // 'stop', 'tool_calls', 'length', 'error'
        public array $toolCalls = [],
        public ?array $usage = null,
    ) {}

    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    public function isComplete(): bool
    {
        return $this->finishReason === 'stop' || $this->finishReason === 'end_turn';
    }
}
