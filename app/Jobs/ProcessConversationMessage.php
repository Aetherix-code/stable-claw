<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\SecretaryMessage;
use App\Models\TelegramSetting;
use App\Services\AI\AgentLoop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
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
            $response = $agentLoop->run($this->conversation, $this->userMessage);

            $this->sendTelegramReplyIfNeeded($response);
        } finally {
            $this->conversation->update(['is_processing' => false]);
        }
    }

    private function sendTelegramReplyIfNeeded(SecretaryMessage $response): void
    {
        if ($this->conversation->channel !== 'telegram' || ! $this->conversation->telegram_chat_id) {
            return;
        }

        $token = TelegramSetting::instance()->bot_token;

        if (! $token) {
            Log::warning('Telegram bot token not configured, cannot send scheduled job reply.');

            return;
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $this->conversation->telegram_chat_id,
            'text' => $response->content ?? 'Done.',
            'parse_mode' => 'Markdown',
        ]);
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
