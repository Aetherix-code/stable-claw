<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\AIToolCall;
use OpenAI;
use OpenAI\Client;

/**
 * Ollama provider — uses the OpenAI-compatible API endpoint.
 */
class OllamaProvider implements AIProvider
{
    private readonly Client $client;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
    ) {
        $this->client = OpenAI::factory()
            ->withBaseUri($this->baseUrl)
            ->withApiKey('ollama') // Ollama ignores the key but the client requires one
            ->make();
    }

    public function chat(array $messages, array $tools = [], array $options = []): AIResponse
    {
        $params = [
            'model' => $this->model,
            'messages' => $messages,
            ...$options,
        ];

        // Only pass tools if the model supports them (optional)
        if (! empty($tools)) {
            $params['tools'] = $tools;
        }

        $response = $this->client->chat()->create($params);
        $choice = $response->choices[0];
        $message = $choice->message;

        $toolCalls = [];
        foreach ($message->toolCalls ?? [] as $call) {
            $rawArgs = $call->function->arguments ?? '{}';
            $decoded = json_decode($rawArgs, true);
            $toolCalls[] = new AIToolCall(
                id: $call->id,
                name: $call->function->name,
                arguments: $decoded ?? [],
            );
        }

        $finishReason = match ($choice->finishReason) {
            'tool_calls' => 'tool_calls',
            'stop' => 'stop',
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
