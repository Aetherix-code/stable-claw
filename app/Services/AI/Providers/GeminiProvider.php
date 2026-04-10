<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\AIToolCall;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AIProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
    ) {}

    public function chat(array $messages, array $tools = [], array $options = []): AIResponse
    {
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemInstruction = ['parts' => [['text' => $msg['content']]]];

                continue;
            }

            $role = $msg['role'] === 'assistant' ? 'model' : 'user';

            if ($msg['role'] === 'tool') {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $msg['name'] ?? 'unknown',
                            'response' => ['content' => $msg['content']],
                        ],
                    ]],
                ];

                continue;
            }

            if (! empty($msg['tool_calls'])) {
                $parts = [];
                foreach ($msg['tool_calls'] as $call) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $call['function']['name'],
                            'args' => json_decode($call['function']['arguments'], true) ?? [],
                        ],
                    ];
                }
                $contents[] = ['role' => 'model', 'parts' => $parts];

                continue;
            }

            $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content'] ?? '']]];
        }

        $payload = ['contents' => $contents];

        if ($systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        if (! empty($tools)) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(fn ($t) => [
                    'name' => $t['function']['name'],
                    'description' => $t['function']['description'] ?? '',
                    'parameters' => $t['function']['parameters'] ?? ['type' => 'OBJECT', 'properties' => new \stdClass],
                ], $tools),
            ]];
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
        $response = Http::post($url, $payload);
        $data = $response->json();

        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];

        $textContent = null;
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textContent = $part['text'];
            }

            if (isset($part['functionCall'])) {
                $args = $part['functionCall']['args'] ?? [];
                $toolCalls[] = new AIToolCall(
                    id: uniqid('gemini_', true),
                    name: $part['functionCall']['name'],
                    arguments: $args,
                    rawArguments: json_encode($args) ?: '{}',
                );
            }
        }

        $stopReason = $candidate['finishReason'] ?? 'STOP';
        $finishReason = match ($stopReason) {
            'STOP' => 'stop',
            'TOOL_USE',
            'FUNCTION_CALL' => 'tool_calls',
            default => 'stop',
        };

        if (! empty($toolCalls)) {
            $finishReason = 'tool_calls';
        }

        return new AIResponse(
            content: $textContent,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            usage: [
                'input_tokens' => $data['usageMetadata']['promptTokenCount'] ?? null,
                'output_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? null,
            ],
        );
    }
}
