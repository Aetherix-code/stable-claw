<?php

namespace App\Services\Tools;

use App\Models\Skill;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;

class UpdateSkillTool extends Tool
{
    public function name(): string
    {
        return 'update_skill';
    }

    public function description(): string
    {
        return 'Update an existing skill in the skill library. Use this after the user approves changes to save the refined skill definition. You can update any combination of fields.';
    }

    public function parameters(): array
    {
        return [
            'skill_id' => [
                'type' => 'integer',
                'description' => 'The ID of the skill to update.',
            ],
            'name' => [
                'type' => 'string',
                'description' => 'The new name of the skill.',
            ],
            'description' => [
                'type' => 'string',
                'description' => 'A clear description of what the skill does and when it should be used.',
            ],
            'detailed_instructions' => [
                'type' => 'string',
                'description' => 'Comprehensive reference document covering API endpoints, credentials, data mappings, pagination, quotas, and any technical details needed to reproduce the workflow.',
            ],
            'trigger_keywords' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Words or phrases that should trigger this skill.',
            ],
            'steps' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                        'tool' => ['type' => 'string'],
                        'action' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                ],
                'description' => 'Ordered list of steps to perform this skill.',
            ],
        ];
    }

    public function required(): array
    {
        return ['skill_id'];
    }

    public function execute(array $parameters): ToolResult
    {
        $skill = Skill::find($parameters['skill_id']);

        if (! $skill) {
            return ToolResult::error("Skill with ID {$parameters['skill_id']} not found.");
        }

        $updates = array_filter([
            'name' => $parameters['name'] ?? null,
            'description' => $parameters['description'] ?? null,
            'detailed_instructions' => $parameters['detailed_instructions'] ?? null,
            'trigger_keywords' => $parameters['trigger_keywords'] ?? null,
            'steps' => $parameters['steps'] ?? null,
        ], fn ($v) => $v !== null);

        if (empty($updates)) {
            return ToolResult::error('No fields provided to update.');
        }

        $skill->update($updates);

        return ToolResult::success([
            'updated' => true,
            'skill_id' => $skill->id,
            'name' => $skill->name,
            'fields_updated' => array_keys($updates),
        ]);
    }
}
