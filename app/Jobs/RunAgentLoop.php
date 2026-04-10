<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\AI\AgentLoop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAgentLoop implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $conversationId,
        public readonly string $userMessage,
    ) {}

    public function handle(AgentLoop $loop): void
    {
        $conversation = Conversation::findOrFail($this->conversationId);

        $loop->run($conversation, $this->userMessage);
    }
}
