<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\SecretaryMessage;
use App\Services\AI\AgentLoop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessConversationMessage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly SecretaryMessage $userMessage,
    ) {}

    public function handle(AgentLoop $agentLoop): void
    {
        try {
            $agentLoop->run($this->conversation, $this->userMessage);
        } finally {
            $this->conversation->update(['is_processing' => false]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessConversationMessage job failed', [
            'conversation_id' => $this->conversation->id,
            'error' => $exception->getMessage(),
        ]);

        $this->conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Sorry, something went wrong while processing your message. Please try again.',
        ]);

        $this->conversation->update(['is_processing' => false]);
    }
}
