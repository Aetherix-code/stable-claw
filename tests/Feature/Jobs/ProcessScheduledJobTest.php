<?php

use App\Jobs\ProcessConversationMessage;
use App\Jobs\ProcessScheduledJob;
use App\Models\Conversation;
use App\Models\ScheduledJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

test('creates web conversation and dispatches message processing', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $job = ScheduledJob::factory()->create([
        'respond_channel' => 'web',
        'scheduled_at' => now()->subMinute(),
    ]);

    (new ProcessScheduledJob($job))->handle();

    $conversation = Conversation::where('scheduled_job_id', $job->id)->first();

    expect($conversation)->not->toBeNull();
    expect($conversation->channel)->toBe('web');
    expect($conversation->title)->toBe($job->title);
    expect($conversation->messages)->toHaveCount(1);
    expect($conversation->messages->first()->content)->toContain($job->prompt);

    Queue::assertPushed(ProcessConversationMessage::class);
});

test('advances next run for recurring jobs', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $job = ScheduledJob::factory()->recurring('daily')->create([
        'scheduled_at' => now()->subMinute(),
    ]);

    $originalScheduledAt = $job->scheduled_at;

    (new ProcessScheduledJob($job))->handle();

    $job->refresh();
    expect($job->is_active)->toBeTrue();
    expect($job->last_run_at)->not->toBeNull();
    expect($job->scheduled_at->gt($originalScheduledAt))->toBeTrue();
});

test('deactivates one-time jobs after execution', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $job = ScheduledJob::factory()->create([
        'frequency' => 'once',
        'scheduled_at' => now()->subMinute(),
    ]);

    (new ProcessScheduledJob($job))->handle();

    $job->refresh();
    expect($job->is_active)->toBeFalse();
});

test('manual trigger does not update scheduled_at or last_run_at', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $job = ScheduledJob::factory()->recurring('daily')->create([
        'scheduled_at' => now()->addHour(),
    ]);

    $originalScheduledAt = $job->scheduled_at;

    (new ProcessScheduledJob($job, isManualTrigger: true))->handle();

    $job->refresh();
    expect($job->scheduled_at->equalTo($originalScheduledAt))->toBeTrue();
    expect($job->last_run_at)->toBeNull();
    expect($job->is_active)->toBeTrue();
});

test('manual trigger does not deactivate one-time jobs', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $job = ScheduledJob::factory()->create([
        'frequency' => 'once',
        'scheduled_at' => now()->addHour(),
    ]);

    (new ProcessScheduledJob($job, isManualTrigger: true))->handle();

    $job->refresh();
    expect($job->is_active)->toBeTrue();
    expect($job->last_run_at)->toBeNull();
});

test('message includes scheduled job context', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $job = ScheduledJob::factory()->create([
        'title' => 'Morning Report',
        'frequency' => 'daily',
        'respond_channel' => 'telegram',
        'scheduled_at' => now()->subMinute(),
    ]);

    (new ProcessScheduledJob($job))->handle();

    $conversation = Conversation::where('scheduled_job_id', $job->id)->first();
    $message = $conversation->messages->first();

    expect($message->content)->toContain('Scheduled Job: "Morning Report"');
    expect($message->content)->toContain($job->prompt);
    expect($message->content)->not->toContain('Manually triggered');
});

test('manual trigger message includes manually triggered label', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $job = ScheduledJob::factory()->create([
        'title' => 'Test Job',
        'scheduled_at' => now()->addHour(),
    ]);

    (new ProcessScheduledJob($job, isManualTrigger: true))->handle();

    $conversation = Conversation::where('scheduled_job_id', $job->id)->first();
    $message = $conversation->messages->first();

    expect($message->content)->toContain('Manually triggered');
    expect($message->content)->toContain($job->prompt);
});

test('reuses recent telegram conversation within timeout', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $user = User::factory()->create([
        'telegram_username' => 'testuser',
        'telegram_chat_id' => '123456',
    ]);

    $existing = Conversation::create([
        'user_id' => $user->id,
        'channel' => 'telegram',
        'telegram_chat_id' => '123456',
        'title' => 'Old telegram chat',
    ]);

    $existing->messages()->create([
        'role' => 'user',
        'content' => 'recent message',
        'created_at' => now()->subMinutes(5),
    ]);

    $job = ScheduledJob::factory()->create([
        'user_id' => $user->id,
        'respond_channel' => 'telegram',
        'scheduled_at' => now()->subMinute(),
    ]);

    (new ProcessScheduledJob($job))->handle();

    expect($existing->fresh()->messages)->toHaveCount(2);
    Queue::assertPushed(ProcessConversationMessage::class);
});

test('creates new telegram conversation when none is recent', function () {
    Queue::fake([ProcessConversationMessage::class]);

    $user = User::factory()->create([
        'telegram_username' => 'testuser',
        'telegram_chat_id' => '123456',
    ]);

    $old = Conversation::create([
        'user_id' => $user->id,
        'channel' => 'telegram',
        'telegram_chat_id' => '123456',
        'title' => 'Old telegram chat',
    ]);

    $oldMessage = $old->messages()->create([
        'role' => 'user',
        'content' => 'old message',
    ]);

    // Backdate both so they fall outside the conversation timeout window
    DB::table('secretary_messages')->where('id', $oldMessage->id)->update(['created_at' => now()->subHours(2)]);
    DB::table('conversations')->where('id', $old->id)->update(['updated_at' => now()->subHours(2)]);

    $job = ScheduledJob::factory()->create([
        'user_id' => $user->id,
        'respond_channel' => 'telegram',
        'scheduled_at' => now()->subMinute(),
    ]);

    (new ProcessScheduledJob($job))->handle();

    $newConversation = Conversation::where('scheduled_job_id', $job->id)->first();
    expect($newConversation)->not->toBeNull();
    expect($newConversation->id)->not->toBe($old->id);
    expect($newConversation->channel)->toBe('telegram');
    expect($newConversation->telegram_chat_id)->toBe('123456');

    Queue::assertPushed(ProcessConversationMessage::class);
});
