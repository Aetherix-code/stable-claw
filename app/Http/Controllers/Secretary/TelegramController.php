<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\TelegramSetting;
use App\Models\User;
use App\Services\AI\AgentLoop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function __construct(
        private readonly AgentLoop $agentLoop,
    ) {}

    /**
     * Receive an incoming Telegram webhook update.
     */
    public function webhook(Request $request): JsonResponse
    {
        $update = $request->json()->all();

        Log::debug('Telegram update received', ['update' => $update]);

        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if (! $message || empty($message['text'])) {
            return response()->json(['ok' => true]);
        }

        $settings = TelegramSetting::instance();

        $chatId = (string) $message['chat']['id'];
        $text = $message['text'];
        $fromUsername = $message['from']['username'] ?? null;

        // Only allow configured Telegram users
        $allowedUsernames = $settings->allowed_usernames;
        if (! empty($allowedUsernames) && ! in_array($fromUsername, $allowedUsernames, true)) {
            $this->sendTelegramMessage($chatId, 'Sorry, you are not authorized to use this bot.', $settings);

            return response()->json(['ok' => true]);
        }

        // Find or create the system user that owns all Telegram conversations
        $user = User::firstOrCreate(
            ['email' => 'telegram@secretary.local'],
            [
                'name' => 'Telegram Secretary',
                'password' => Hash::make(str()->random(32)),
            ]
        );

        $conversation = $this->resolveConversation($user, $chatId, $settings);

        try {
            $response = $this->agentLoop->run($conversation, $text);
            $reply = $response->content ?? 'Done.';
        } catch (\Throwable $e) {
            Log::error('Telegram AgentLoop error', ['error' => $e->getMessage()]);
            $reply = 'Sorry, something went wrong. Please try again.';
        }

        $this->sendTelegramMessage($chatId, $reply, $settings);

        return response()->json(['ok' => true]);
    }

    /**
     * Find an active conversation or create a new one based on timeout settings.
     */
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

    private function sendTelegramMessage(string $chatId, string $text, TelegramSetting $settings): void
    {
        $token = $settings->bot_token;

        if (! $token) {
            Log::warning('Telegram bot token not configured.');

            return;
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }
}
