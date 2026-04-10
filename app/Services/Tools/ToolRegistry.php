<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\Tool;
use InvalidArgumentException;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): Tool
    {
        if (! isset($this->tools[$name])) {
            throw new InvalidArgumentException("Tool [{$name}] is not registered.");
        }

        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return Tool[]
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * Return all tools formatted for the AI provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toAIFormat(): array
    {
        return array_map(fn (Tool $tool) => $tool->toArray(), array_values($this->tools));
    }
}
