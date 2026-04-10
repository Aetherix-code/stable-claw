<?php

namespace App\Services\Tools;

use App\Models\Conversation;
use App\Services\Tools\Contracts\NeedsConversationContext;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;

class StartLearnModeTool extends Tool implements NeedsConversationContext
{
    private ?Conversation $conversation = null;

    public function setConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function name(): string
    {
        return 'start_recording';
    }

    public function description(): string
    {
        return 'Start recording a new skill. Call this when the user wants to teach you a new workflow or task. After starting, guide the user step-by-step through the demonstration.';
    }

    public function parameters(): array
    {
        return [
            'skill_name' => [
                'type' => 'string',
                'description' => 'A short, descriptive name for the skill being recorded.',
            ],
        ];
    }

    public function required(): array
    {
        return ['skill_name'];
    }

    public function execute(array $parameters): ToolResult
    {
        if (! $this->conversation) {
            return ToolResult::error('No conversation context available.');
        }

        $this->conversation->startLearnMode($parameters['skill_name']);

        return ToolResult::success([
            'recording' => true,
            'skill_name' => $parameters['skill_name'],
        ]);
    }
}
