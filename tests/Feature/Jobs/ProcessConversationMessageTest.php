<?php

use App\Jobs\ProcessConversationMessage;
use App\Models\Conversation;
use App\Models\SecretaryMessage;
use App\Models\TelegramSetting;
use App\Models\User;
use App\Services\AI\AgentLoop;
use Illuminate\Support\Facades\Http;

test('sends telegram message after processing when conversation is telegram channel', function () {
    Http::fake();

    $settings = TelegramSetting::instance();
    $settings->update(['bot_token' => 'test-bot-token']);

    $user = User::factory()->create(['telegram_username' => '@testuser']);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'channel' => 'telegram',
        'telegram_chat_id' => '123456',
        'title' => 'Scheduled job convo',
    ]);

    $userMessage = $conversation->messages()->create([
        'role' => 'user',
        'content' => 'Check weather',
    ]);

    $assistantMessage = new SecretaryMessage([
        'role' => 'assistant',
        'content' => 'The weather is sunny.',
    ]);

    $agentLoop = $this->mock(AgentLoop::class);
    $agentLoop->shouldReceive('run')
        ->once()
        ->andReturn($assistantMessage);

    (new ProcessConversationMessage($conversation, $userMessage))->handle($agentLoop);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === '123456'
            && $request['text'] === 'The weather is sunny.';
    });
});

test('does not send telegram message for web conversations', function () {
    Http::fake();

    $user = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'channel' => 'web',
        'title' => 'Web convo',
    ]);

    $userMessage = $conversation->messages()->create([
        'role' => 'user',
        'content' => 'Hello',
    ]);

    $assistantMessage = new SecretaryMessage([
        'role' => 'assistant',
        'content' => 'Hi there!',
    ]);

    $agentLoop = $this->mock(AgentLoop::class);
    $agentLoop->shouldReceive('run')
        ->once()
        ->andReturn($assistantMessage);

    (new ProcessConversationMessage($conversation, $userMessage))->handle($agentLoop);

    Http::assertNothingSent();
});

test('does not send telegram message when chat_id is missing', function () {
    Http::fake();

    $user = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'channel' => 'telegram',
        'title' => 'Telegram no chat id',
    ]);

    $userMessage = $conversation->messages()->create([
        'role' => 'user',
        'content' => 'Hello',
    ]);

    $assistantMessage = new SecretaryMessage([
        'role' => 'assistant',
        'content' => 'Hi there!',
    ]);

    $agentLoop = $this->mock(AgentLoop::class);
    $agentLoop->shouldReceive('run')
        ->once()
        ->andReturn($assistantMessage);

    (new ProcessConversationMessage($conversation, $userMessage))->handle($agentLoop);

    Http::assertNothingSent();
});
