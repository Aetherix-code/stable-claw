<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\AIToolCall;
use OpenAI\Client;

class OpenAIProvider implements AIProvider
{
    public function __construct(
        private readonly Client $client,
        private readonly string $model,
    ) {}

    public function chat(array $messages, array $tools = [], array $options = []): AIResponse
    {
        $params = [
            'model' => $this->model,
            'messages' => $messages,
            ...$options,
        ];

        if (! empty($tools)) {
            $params['tools'] = $tools;
        }

        $response = $this->client->chat()->create($params);
        $choice = $response->choices[0];
        $message = $choice->message;

        $toolCalls = [];
        foreach ($message->toolCalls ?? [] as $call) {
            $raw = $call->function->arguments ?? '{}';
            $toolCalls[] = new AIToolCall(
                id: $call->id,
                name: $call->function->name,
                arguments: json_decode($raw, true) ?? [],
                rawArguments: $raw,
            );
        }

        $finishReason = match ($choice->finishReason) {
            'tool_calls' => 'tool_calls',
            'stop' => 'stop',
            'length' => 'length',
            default => $choice->finishReason ?? 'stop',
        };

        return new AIResponse(
            content: $message->content,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            usage: [
                'input_tokens' => $response->usage?->promptTokens,
                'output_tokens' => $response->usage?->completionTokens,
            ],
        );
    }
}
