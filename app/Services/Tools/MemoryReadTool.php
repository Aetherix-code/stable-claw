<?php

namespace App\Services\Tools;

use App\Models\Memory;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;

class MemoryReadTool extends Tool
{
    public function name(): string
    {
        return 'memory_read';
    }

    public function description(): string
    {
        return 'Read from persistent memory. Use action=list to see all stored keys (with type and description). Use action=read with a key to retrieve a specific value.';
    }

    public function parameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => ['list', 'read'],
                'description' => 'list: return all memory keys with metadata. read: return the value for a specific key.',
            ],
            'key' => [
                'type' => 'string',
                'description' => 'The memory key to look up (required when action=read).',
            ],
        ];
    }

    public function required(): array
    {
        return ['action'];
    }

    public function execute(array $parameters): ToolResult
    {
        return match ($parameters['action'] ?? 'read') {
            'list' => $this->list(),
            'read' => $this->read($parameters['key'] ?? ''),
            default => ToolResult::error("Unknown action: {$parameters['action']}"),
        };
    }

    private function list(): ToolResult
    {
        $keys = Memory::orderBy('key')->get(['key', 'type', 'description', 'is_sensitive'])->map(fn ($m) => [
            'key' => $m->key,
            'type' => $m->type,
            'description' => $m->description,
            'sensitive' => $m->is_sensitive,
        ]);

        return ToolResult::success(['keys' => $keys, 'count' => $keys->count()]);
    }

    private function read(string $key): ToolResult
    {
        if ($key === '') {
            return ToolResult::error('key is required for action=read');
        }

        $value = Memory::recall($key);

        if ($value === null) {
            return ToolResult::error("No memory found for key: {$key}");
        }

        return ToolResult::success(['key' => $key, 'value' => $value]);
    }
}
