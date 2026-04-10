<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
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

        $chatId = (string) $message['chat']['id'];
        $text = $message['text'];
        $fromUsername = $message['from']['username'] ?? null;

        // Only allow the configured Telegram user
        $allowedUsername = config('services.telegram.allowed_username');
        if ($allowedUsername && $fromUsername !== $allowedUsername) {
            $this->sendTelegramMessage($chatId, 'Sorry, you are not authorized to use this bot.');

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

        $conversation = Conversation::firstOrCreate(
            ['channel' => 'telegram', 'telegram_chat_id' => $chatId],
            ['user_id' => $user->id, 'title' => "Telegram: {$chatId}"]
        );

        try {
            $response = $this->agentLoop->run($conversation, $text);
            $reply = $response->content ?? 'Done.';
        } catch (\Throwable $e) {
            Log::error('Telegram AgentLoop error', ['error' => $e->getMessage()]);
            $reply = 'Sorry, something went wrong. Please try again.';
        }

        $this->sendTelegramMessage($chatId, $reply);

        return response()->json(['ok' => true]);
    }

    private function sendTelegramMessage(string $chatId, string $text): void
    {
        $token = config('services.telegram.bot_token');

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
