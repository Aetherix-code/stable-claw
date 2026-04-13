<?php

namespace App\Services\Tools;

use App\Models\Conversation;
use App\Models\ScheduledJob;
use App\Services\Tools\Contracts\NeedsConversationContext;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Exception;

class SchedulerTool extends Tool implements NeedsConversationContext
{
    private ?Conversation $conversation = null;

    public function setConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function name(): string
    {
        return 'scheduler';
    }

    public function description(): string
    {
        return 'Manage scheduled jobs. Actions: create (schedule a new job/reminder, if it\'s a reminder then start the job prompt with "This is a scheduled message for you to remind me:"), list (view scheduled jobs), cancel (deactivate a scheduled job). Use this to set reminders or recurring tasks for the user.';
    }

    public function parameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => ['create', 'list', 'cancel'],
                'description' => 'The scheduler action to perform.',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Short title for the scheduled job (for action=create).',
            ],
            'prompt' => [
                'type' => 'string',
                'description' => 'The message/task to deliver when the job fires (for action=create).',
            ],
            'frequency' => [
                'type' => 'string',
                'enum' => ['once', 'hourly', 'daily', 'weekly', 'monthly'],
                'description' => 'How often to run the job (for action=create). Default: once.',
            ],
            'scheduled_at' => [
                'type' => 'string',
                'description' => 'ISO 8601 datetime for when the job should first run (for action=create). Example: 2026-04-13T08:00:00.',
            ],
            'respond_channel' => [
                'type' => 'string',
                'enum' => ['web', 'telegram'],
                'description' => 'Channel to respond on when the job fires (for action=create). If it is a reminder then explicitly set to telegram, otherwise: web.',
            ],
            'id' => [
                'type' => 'integer',
                'description' => 'The scheduled job ID (for action=cancel).',
            ],
        ];
    }

    public function required(): array
    {
        return ['action'];
    }

    public function execute(array $parameters): ToolResult
    {
        try {
            $user = $this->conversation?->user;

            if ($user === null) {
                return ToolResult::error('No user context available.');
            }

            return match ($parameters['action']) {
                'create' => $this->createJob($parameters),
                'list' => $this->listJobs(),
                'cancel' => $this->cancelJob($parameters),
                default => ToolResult::error("Unknown action: {$parameters['action']}"),
            };
        } catch (Exception $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    private function createJob(array $parameters): ToolResult
    {
        foreach (['title', 'prompt', 'scheduled_at'] as $field) {
            if (! isset($parameters[$field]) || $parameters[$field] === '') {
                return ToolResult::error("The \"{$field}\" parameter is required for action=create.");
            }
        }

        $respondChannel = $parameters['respond_channel']
            ?? ($this->conversation->user->telegram_chat_id ? 'telegram' : ($this->conversation->channel ?? 'web'));

        $job = $this->conversation->user->scheduledJobs()->create([
            'conversation_id' => $this->conversation->id,
            'title' => $parameters['title'],
            'prompt' => $parameters['prompt'],
            'source' => 'agent',
            'frequency' => $parameters['frequency'] ?? 'once',
            'respond_channel' => $respondChannel,
            'scheduled_at' => $parameters['scheduled_at'],
        ]);

        return ToolResult::success([
            'message' => "Scheduled job \"{$job->title}\" created successfully.",
            'id' => $job->id,
            'scheduled_at' => $job->scheduled_at->toIso8601String(),
            'frequency' => $job->frequency,
        ]);
    }

    private function listJobs(): ToolResult
    {
        $jobs = $this->conversation->user->scheduledJobs()
            ->where('is_active', true)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (ScheduledJob $job) => [
                'id' => $job->id,
                'title' => $job->title,
                'prompt' => $job->prompt,
                'frequency' => $job->frequency,
                'source' => $job->source,
                'scheduled_at' => $job->scheduled_at->toIso8601String(),
                'last_run_at' => $job->last_run_at?->toIso8601String(),
            ]);

        return ToolResult::success($jobs->toArray());
    }

    private function cancelJob(array $parameters): ToolResult
    {
        if (! isset($parameters['id'])) {
            return ToolResult::error('The "id" parameter is required for action=cancel.');
        }

        $job = $this->conversation->user->scheduledJobs()
            ->where('id', $parameters['id'])
            ->first();

        if ($job === null) {
            return ToolResult::error("Scheduled job #{$parameters['id']} not found.");
        }

        $job->update(['is_active' => false]);

        return ToolResult::success("Scheduled job \"{$job->title}\" has been cancelled.");
    }
}
