<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\ScheduledJob;
use App\Models\TelegramSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessScheduledJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly ScheduledJob $scheduledJob,
        public readonly bool $isManualTrigger = false,
    ) {}

    public function handle(): void
    {
        $job = $this->scheduledJob;

        $conversation = $this->resolveConversation($job);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $this->buildMessageContent($job),
        ]);

        $conversation->update([
            'is_processing' => true,
            'scheduled_job_id' => $job->id,
        ]);

        if (! $this->isManualTrigger) {
            $job->calculateNextRun();
        }

        ProcessConversationMessage::dispatch($conversation, $userMessage);
    }

    private function resolveConversation(ScheduledJob $job): Conversation
    {
        if ($job->respond_channel === 'telegram') {
            return $this->resolveTelegramConversation($job);
        }

        return $job->user->conversations()->create([
            'channel' => 'web',
            'title' => $job->title,
            'scheduled_job_id' => $job->id,
        ]);
    }

    private function resolveTelegramConversation(ScheduledJob $job): Conversation
    {
        $chatId = $job->user->telegram_chat_id;

        if (! $chatId) {
            Log::warning('ProcessScheduledJob: user has no telegram_chat_id — they must message the bot first', [
                'scheduled_job_id' => $job->id,
                'user_id' => $job->user_id,
            ]);
        }

        $settings = TelegramSetting::instance();
        $cutoff = now()->subMinutes($settings->conversation_timeout_minutes);

        if ($chatId) {
            $existing = Conversation::where('channel', 'telegram')
                ->where('telegram_chat_id', $chatId)
                ->whereHas('messages', fn ($q) => $q->where('created_at', '>', $cutoff))
                ->latest('updated_at')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return $job->user->conversations()->create([
            'channel' => 'telegram',
            'telegram_chat_id' => $chatId,
            'title' => $job->title,
            'scheduled_job_id' => $job->id,
        ]);
    }

    private function buildMessageContent(ScheduledJob $job): string
    {
        $context = '[System: This is a scheduled job that is NOW firing. The user wrote the prompt below in the past and scheduled it for this time. ';
        $context .= 'Your task is to execute the request NOW — do not schedule or defer anything. ';
        $context .= "If the prompt says \"remind me\" or \"tomorrow\" etc., the scheduled time has already arrived, so deliver the reminder directly.]\n\n";
        $context .= "[Scheduled Job: \"{$job->title}\"";

        if ($this->isManualTrigger) {
            $context .= ' | Manually triggered';
        }

        $context .= "]\n\n";

        return $context.$job->prompt;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessScheduledJob failed', [
            'scheduled_job_id' => $this->scheduledJob->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
