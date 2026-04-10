<?php

namespace App\Services\Tools\Contracts;

use App\Services\Tools\DTOs\ToolResult;

abstract class Tool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * JSON Schema properties for the tool's parameters.
     *
     * @return array<string, array{type: string, description: string}>
     */
    abstract public function parameters(): array;

    /**
     * List of required parameter names.
     *
     * @return string[]
     */
    public function required(): array
    {
        return [];
    }

    /**
     * Execute the tool with the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    abstract public function execute(array $parameters): ToolResult;

    /**
     * Return the tool definition in the AI function-calling format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $properties = $this->parameters();

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties !== [] ? $properties : new \stdClass,
                    'required' => $this->required(),
                ],
            ],
        ];
    }
}
