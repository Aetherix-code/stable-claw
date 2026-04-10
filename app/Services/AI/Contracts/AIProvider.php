<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\AIResponse;

interface AIProvider
{
    /**
     * Send a chat completion request.
     *
     * @param  array<int, array{role: string, content: string|array|null, tool_calls?: array|null, tool_call_id?: string|null, name?: string|null}>  $messages
     * @param  array<int, array>  $tools  JSON-schema tool definitions
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $tools = [], array $options = []): AIResponse;
}
