<?php

namespace App\Services\Tools;

use App\Models\Memory;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;

class MemoryWriteTool extends Tool
{
    public function name(): string
    {
        return 'memory_write';
    }

    public function description(): string
    {
        return 'Store a value in persistent memory. Use this to save credentials, preferences, or facts for future use. Mark sensitive=true for passwords and API keys.';
    }

    public function parameters(): array
    {
        return [
            'key' => [
                'type' => 'string',
                'description' => 'A short descriptive key, e.g. "toggl_api_key" or "user_name".',
            ],
            'value' => [
                'type' => 'string',
                'description' => 'The value to store.',
            ],
            'type' => [
                'type' => 'string',
                'enum' => ['fact', 'credential', 'preference'],
                'description' => 'The memory category.',
            ],
            'sensitive' => [
                'type' => 'boolean',
                'description' => 'Set to true for passwords, tokens, or API keys. The value will be encrypted.',
            ],
            'description' => [
                'type' => 'string',
                'description' => 'Optional human-readable description of what this memory is for.',
            ],
        ];
    }

    public function required(): array
    {
        return ['key', 'value'];
    }

    public function execute(array $parameters): ToolResult
    {
        Memory::remember(
            key: $parameters['key'],
            value: $parameters['value'],
            type: $parameters['type'] ?? 'fact',
            sensitive: (bool) ($parameters['sensitive'] ?? false),
            description: $parameters['description'] ?? null,
        );

        return ToolResult::success(['saved' => true, 'key' => $parameters['key']]);
    }
}
