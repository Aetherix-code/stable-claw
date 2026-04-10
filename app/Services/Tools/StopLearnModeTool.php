<?php

namespace App\Services\Tools;

use App\Models\Conversation;
use App\Services\AI\LearnModeService;
use App\Services\Tools\Contracts\NeedsConversationContext;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;

class StopLearnModeTool extends Tool implements NeedsConversationContext
{
    private ?Conversation $conversation = null;

    public function __construct(
        private readonly LearnModeService $learnModeService,
    ) {}

    public function setConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function name(): string
    {
        return 'stop_recording';
    }

    public function description(): string
    {
        return 'Stop the current recording session and save the skill. Call this once the user has fully demonstrated the workflow and you have everything you need.';
    }

    public function parameters(): array
    {
        return [];
    }

    public function execute(array $parameters): ToolResult
    {
        if (! $this->conversation) {
            return ToolResult::error('No conversation context available.');
        }

        if (! $this->conversation->is_learn_mode) {
            return ToolResult::error('No active recording session.');
        }

        $skill = $this->learnModeService->compileSkill($this->conversation);

        if (! $skill) {
            return ToolResult::error('Failed to save the skill.');
        }

        return ToolResult::success([
            'saved' => true,
            'skill_name' => $skill->name,
            'skill_id' => $skill->id,
        ]);
    }
}
