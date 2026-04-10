<?php

namespace App\Services\Tools;

use App\Models\Skill;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;

class ReadSkillTool extends Tool
{
    public function name(): string
    {
        return 'read_skill';
    }

    public function description(): string
    {
        return 'Read a learned skill\'s full step-by-step instructions so you can execute it. Use action=list to see all available skills. Use action=read with a skill_id to get the full steps for a specific skill.';
    }

    public function parameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => ['list', 'read'],
                'description' => 'list: return all skills with id, name, description, and trigger keywords. read: return the full steps for a specific skill.',
            ],
            'skill_id' => [
                'type' => 'integer',
                'description' => 'The ID of the skill to read in full (required when action=read).',
            ],
        ];
    }

    public function required(): array
    {
        return ['action'];
    }

    public function execute(array $parameters): ToolResult
    {
        return match ($parameters['action'] ?? 'list') {
            'list' => $this->list(),
            'read' => $this->read((int) ($parameters['skill_id'] ?? 0)),
            default => ToolResult::error("Unknown action: {$parameters['action']}"),
        };
    }

    private function list(): ToolResult
    {
        $skills = Skill::orderBy('name')->get(['id', 'name', 'description', 'trigger_keywords'])->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'description' => $s->description,
            'trigger_keywords' => $s->trigger_keywords ?? [],
        ]);

        return ToolResult::success(['skills' => $skills, 'count' => $skills->count()]);
    }

    private function read(int $skillId): ToolResult
    {
        if ($skillId === 0) {
            return ToolResult::error('skill_id is required for action=read');
        }

        $skill = Skill::find($skillId);

        if (! $skill) {
            return ToolResult::error("Skill with ID {$skillId} not found.");
        }

        return ToolResult::success([
            'id' => $skill->id,
            'name' => $skill->name,
            'description' => $skill->description,
            'detailed_instructions' => $skill->detailed_instructions,
            'trigger_keywords' => $skill->trigger_keywords ?? [],
            'steps' => $skill->steps ?? [],
            'memory_keys' => $skill->memory_keys ?? [],
        ]);
    }
}
