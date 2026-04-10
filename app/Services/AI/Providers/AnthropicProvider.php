<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\AIToolCall;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements AIProvider
{
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
    ) {}

    public function chat(array $messages, array $tools = [], array $options = []): AIResponse
    {
        // Anthropic separates system message from the messages array
        $systemMessage = null;
        $filteredMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage = $msg['content'];
            } else {
                $filteredMessages[] = $this->formatMessage($msg);
            }
        }

        $payload = [
            'model' => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => $filteredMessages,
        ];

        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }

        if (! empty($tools)) {
            $payload['tools'] = array_map(fn ($t) => [
                'name' => $t['function']['name'],
                'description' => $t['function']['description'] ?? '',
                'input_schema' => $t['function']['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass],
            ], $tools);
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/messages", $payload);

        $data = $response->json();

        $toolCalls = [];
        $textContent = null;

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $textContent = $block['text'];
            }

            if ($block['type'] === 'tool_use') {
                $args = $block['input'] ?? [];
                $toolCalls[] = new AIToolCall(
                    id: $block['id'],
                    name: $block['name'],
                    arguments: $args,
                    rawArguments: json_encode($args) ?: '{}',
                );
            }
        }

        $finishReason = match ($data['stop_reason'] ?? 'end_turn') {
            'tool_use' => 'tool_calls',
            'end_turn' => 'stop',
            default => $data['stop_reason'] ?? 'stop',
        };

        return new AIResponse(
            content: $textContent,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            usage: [
                'input_tokens' => $data['usage']['input_tokens'] ?? null,
                'output_tokens' => $data['usage']['output_tokens'] ?? null,
            ],
        );
    }

    /**
     * Normalise a message to Anthropic format (tool results need special handling).
     */
    private function formatMessage(array $msg): array
    {
        if ($msg['role'] === 'tool') {
            return [
                'role' => 'user',
                'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => $msg['tool_call_id'],
                    'content' => $msg['content'],
                ]],
            ];
        }

        return $msg;
    }
}
