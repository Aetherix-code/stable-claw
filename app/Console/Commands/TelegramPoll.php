<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\TelegramSetting;
use App\Models\User;
use App\Services\AI\AgentLoop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Signature('telegram:poll')]
#[Description('Poll Telegram for new messages via long-polling (getUpdates).')]
class TelegramPoll extends Command
{
    private int $offset = 0;

    public function handle(AgentLoop $agentLoop): int
    {
        $settings = TelegramSetting::instance();
        $token = $settings->bot_token;

        if (! $token) {
            $this->error('Telegram bot token is not configured. Set it in System → Telegram settings.');

            return self::FAILURE;
        }

        // Delete any existing webhook so polling works
        Http::post("https://api.telegram.org/bot{$token}/deleteWebhook");

        $this->info('Polling Telegram for messages... (press Ctrl+C to stop)');

        while (true) {
            try {
                $this->pollOnce($agentLoop, $settings, $token);
            } catch (\Throwable $e) {
                Log::error('Telegram poll error', ['error' => $e->getMessage()]);
                $this->error("Error: {$e->getMessage()}");
                sleep(5);
            }
        }
    }

    private function pollOnce(AgentLoop $agentLoop, TelegramSetting $settings, string $token): void
    {
        $response = Http::timeout(35)->post("https://api.telegram.org/bot{$token}/getUpdates", [
            'offset' => $this->offset,
            'timeout' => 30,
            'allowed_updates' => ['message', 'edited_message'],
        ]);

        if (! $response->successful()) {
            $this->warn('Telegram API error: '.$response->body());
            sleep(5);

            return;
        }

        $updates = $response->json('result', []);

        foreach ($updates as $update) {
            $this->offset = $update['update_id'] + 1;

            $this->processUpdate($update, $agentLoop, $settings, $token);
        }
    }

    private function processUpdate(array $update, AgentLoop $agentLoop, TelegramSetting $settings, string $token): void
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (! $message || empty($message['text'])) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = $message['text'];
        $fromUsername = $message['from']['username'] ?? null;

        $this->line("[{$fromUsername}] {$text}");

        // Only allow configured Telegram users
        $allowedUsernames = $settings->allowed_usernames;
        if (! empty($allowedUsernames) && ! in_array($fromUsername, $allowedUsernames, true)) {
            $this->sendMessage($token, $chatId, 'Sorry, you are not authorized to use this bot.');
            $this->warn("Rejected unauthorized user: {$fromUsername}");

            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'telegram@secretary.local'],
            [
                'name' => 'Telegram Secretary',
                'password' => Hash::make(str()->random(32)),
            ]
        );

        // Link the Telegram chat_id to any user with a matching telegram_username
        if ($fromUsername) {
            User::where('telegram_username', $fromUsername)
                ->orWhere('telegram_username', '@'.$fromUsername)
                ->whereNull('telegram_chat_id')
                ->update(['telegram_chat_id' => $chatId]);
        }

        $conversation = $this->resolveConversation($user, $chatId, $settings);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $text,
        ]);

        try {
            $response = $agentLoop->run($conversation, $userMessage);
            $reply = $response->content ?? 'Done.';
        } catch (\Throwable $e) {
            Log::error('Telegram AgentLoop error', ['error' => $e->getMessage()]);
            $reply = 'Sorry, something went wrong. Please try again.';
        }

        $this->sendMessage($token, $chatId, $reply);
        $this->info("→ Replied to [{$fromUsername}]");
    }

    private function resolveConversation(User $user, string $chatId, TelegramSetting $settings): Conversation
    {
        $latestConversation = Conversation::where('channel', 'telegram')
            ->where('telegram_chat_id', $chatId)
            ->latest()
            ->first();

        if ($latestConversation) {
            $lastMessage = $latestConversation->messages()->latest('created_at')->first();

            $cutoff = now()->subMinutes($settings->conversation_timeout_minutes);

            if ($lastMessage && $lastMessage->created_at->greaterThan($cutoff)) {
                return $latestConversation;
            }
        }

        return Conversation::create([
            'user_id' => $user->id,
            'channel' => 'telegram',
            'telegram_chat_id' => $chatId,
            'title' => "Telegram: {$chatId}",
        ]);
    }

    private function sendMessage(string $token, string $chatId, string $text): void
    {
        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }
}
