<?php

use App\Models\Conversation;
use App\Models\ScheduledJob;
use App\Models\User;
use App\Services\Tools\SchedulerTool;

function createSchedulerTool(): SchedulerTool
{
    return new SchedulerTool;
}

function createConversationForScheduler(): Conversation
{
    $user = User::factory()->create();

    return Conversation::create([
        'user_id' => $user->id,
        'channel' => 'web',
        'title' => 'Test',
    ]);
}

test('create schedules a new job', function () {
    $tool = createSchedulerTool();
    $conversation = createConversationForScheduler();
    $tool->setConversation($conversation);

    $result = $tool->execute([
        'action' => 'create',
        'title' => 'Coffee reminder',
        'prompt' => 'Remind me to get coffee',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data['message'])->toContain('Coffee reminder');

    $this->assertDatabaseHas('scheduled_jobs', [
        'user_id' => $conversation->user_id,
        'title' => 'Coffee reminder',
        'source' => 'agent',
        'frequency' => 'once',
    ]);
});

test('create with recurring frequency', function () {
    $tool = createSchedulerTool();
    $conversation = createConversationForScheduler();
    $tool->setConversation($conversation);

    $result = $tool->execute([
        'action' => 'create',
        'title' => 'Daily standup',
        'prompt' => 'Remind team about standup',
        'frequency' => 'daily',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ]);

    expect($result->success)->toBeTrue();

    $this->assertDatabaseHas('scheduled_jobs', [
        'title' => 'Daily standup',
        'frequency' => 'daily',
    ]);
});

test('create validates required fields', function () {
    $tool = createSchedulerTool();
    $conversation = createConversationForScheduler();
    $tool->setConversation($conversation);

    $result = $tool->execute(['action' => 'create']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('title');
});

test('list returns active jobs', function () {
    $tool = createSchedulerTool();
    $conversation = createConversationForScheduler();
    $tool->setConversation($conversation);

    ScheduledJob::factory()->create([
        'user_id' => $conversation->user_id,
        'title' => 'Active job',
        'is_active' => true,
    ]);

    ScheduledJob::factory()->create([
        'user_id' => $conversation->user_id,
        'title' => 'Inactive job',
        'is_active' => false,
    ]);

    $result = $tool->execute(['action' => 'list']);

    expect($result->success)->toBeTrue();
    expect($result->data)->toHaveCount(1);
    expect($result->data[0]['title'])->toBe('Active job');
});

test('cancel deactivates a job', function () {
    $tool = createSchedulerTool();
    $conversation = createConversationForScheduler();
    $tool->setConversation($conversation);

    $job = ScheduledJob::factory()->create([
        'user_id' => $conversation->user_id,
        'is_active' => true,
    ]);

    $result = $tool->execute(['action' => 'cancel', 'id' => $job->id]);

    expect($result->success)->toBeTrue();
    expect($job->fresh()->is_active)->toBeFalse();
});

test('cancel returns error for nonexistent job', function () {
    $tool = createSchedulerTool();
    $conversation = createConversationForScheduler();
    $tool->setConversation($conversation);

    $result = $tool->execute(['action' => 'cancel', 'id' => 9999]);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('not found');
});

test('cancel prevents cancelling another user job', function () {
    $tool = createSchedulerTool();
    $conversation = createConversationForScheduler();
    $tool->setConversation($conversation);

    $otherUser = User::factory()->create();
    $job = ScheduledJob::factory()->create(['user_id' => $otherUser->id]);

    $result = $tool->execute(['action' => 'cancel', 'id' => $job->id]);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('not found');
});

test('returns error without conversation context', function () {
    $tool = createSchedulerTool();

    $result = $tool->execute(['action' => 'list']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('user context');
});

test('create defaults to telegram when user has telegram_chat_id', function () {
    $tool = createSchedulerTool();
    $user = User::factory()->create(['telegram_chat_id' => '123456']);
    $conversation = Conversation::create([
        'user_id' => $user->id,
        'channel' => 'web',
        'title' => 'Test',
    ]);
    $tool->setConversation($conversation);

    $result = $tool->execute([
        'action' => 'create',
        'title' => 'Dog reminder',
        'prompt' => 'Remind me to take out the dog',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ]);

    expect($result->success)->toBeTrue();

    $this->assertDatabaseHas('scheduled_jobs', [
        'user_id' => $user->id,
        'title' => 'Dog reminder',
        'respond_channel' => 'telegram',
    ]);
});

test('create defaults to web when user has no telegram_chat_id', function () {
    $tool = createSchedulerTool();
    $conversation = createConversationForScheduler();
    $tool->setConversation($conversation);

    $result = $tool->execute([
        'action' => 'create',
        'title' => 'Web reminder',
        'prompt' => 'Remind me about something',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ]);

    expect($result->success)->toBeTrue();

    $this->assertDatabaseHas('scheduled_jobs', [
        'user_id' => $conversation->user_id,
        'title' => 'Web reminder',
        'respond_channel' => 'web',
    ]);
});

test('create respects explicit respond_channel even with telegram linked', function () {
    $tool = createSchedulerTool();
    $user = User::factory()->create(['telegram_chat_id' => '123456']);
    $conversation = Conversation::create([
        'user_id' => $user->id,
        'channel' => 'web',
        'title' => 'Test',
    ]);
    $tool->setConversation($conversation);

    $result = $tool->execute([
        'action' => 'create',
        'title' => 'Web task',
        'prompt' => 'Do something',
        'respond_channel' => 'web',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ]);

    expect($result->success)->toBeTrue();

    $this->assertDatabaseHas('scheduled_jobs', [
        'user_id' => $user->id,
        'title' => 'Web task',
        'respond_channel' => 'web',
    ]);
});
