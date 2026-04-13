<?php

use App\Jobs\ProcessScheduledJob;
use App\Models\ScheduledJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('index page shows scheduled jobs', function () {
    ScheduledJob::factory()->create(['user_id' => $this->user->id, 'title' => 'Morning reminder']);

    $this->get('/system/scheduled-jobs')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('scheduled-jobs/Index')
            ->has('jobs', 1)
            ->where('jobs.0.title', 'Morning reminder')
        );
});

test('index separates archived one-time jobs', function () {
    ScheduledJob::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Active daily',
        'frequency' => 'daily',
        'is_active' => true,
    ]);
    ScheduledJob::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Paused once',
        'frequency' => 'once',
        'is_active' => false,
    ]);
    ScheduledJob::factory()->create([
        'user_id' => $this->user->id,
        'title' => 'Completed once',
        'frequency' => 'once',
        'is_active' => false,
        'last_run_at' => now()->subHour(),
    ]);

    $this->get('/system/scheduled-jobs')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('jobs', 2)
            ->has('archivedJobs', 1)
            ->where('archivedJobs.0.title', 'Completed once')
        );
});

test('index does not show other users jobs', function () {
    $otherUser = User::factory()->create();
    ScheduledJob::factory()->create(['user_id' => $otherUser->id]);

    $this->get('/system/scheduled-jobs')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('jobs', 0));
});

test('store creates a new scheduled job', function () {
    $this->post('/system/scheduled-jobs', [
        'title' => 'Get coffee',
        'prompt' => 'Remind me to get coffee',
        'frequency' => 'daily',
        'respond_channel' => 'web',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertRedirect();

    $this->assertDatabaseHas('scheduled_jobs', [
        'user_id' => $this->user->id,
        'title' => 'Get coffee',
        'source' => 'manual',
        'frequency' => 'daily',
        'respond_channel' => 'web',
    ]);
});

test('store validates required fields', function () {
    $this->post('/system/scheduled-jobs', [])
        ->assertSessionHasErrors(['title', 'prompt', 'frequency', 'respond_channel', 'scheduled_at']);
});

test('store validates frequency values', function () {
    $this->post('/system/scheduled-jobs', [
        'title' => 'Test',
        'prompt' => 'Test prompt',
        'frequency' => 'biweekly',
        'respond_channel' => 'web',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertSessionHasErrors(['frequency']);
});

test('store validates scheduled_at is in the future', function () {
    $this->post('/system/scheduled-jobs', [
        'title' => 'Test',
        'prompt' => 'Test prompt',
        'frequency' => 'once',
        'respond_channel' => 'web',
        'scheduled_at' => now()->subHour()->toIso8601String(),
    ])->assertSessionHasErrors(['scheduled_at']);
});

test('store validates respond_channel values', function () {
    $this->post('/system/scheduled-jobs', [
        'title' => 'Test',
        'prompt' => 'Test prompt',
        'frequency' => 'once',
        'respond_channel' => 'sms',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertSessionHasErrors(['respond_channel']);
});

test('update modifies a scheduled job', function () {
    $job = ScheduledJob::factory()->create(['user_id' => $this->user->id]);

    $this->patch("/system/scheduled-jobs/{$job->id}", [
        'title' => 'Updated title',
    ])->assertRedirect();

    expect($job->fresh()->title)->toBe('Updated title');
});

test('update toggles is_active', function () {
    $job = ScheduledJob::factory()->create(['user_id' => $this->user->id, 'is_active' => true]);

    $this->patch("/system/scheduled-jobs/{$job->id}", [
        'is_active' => false,
    ])->assertRedirect();

    expect($job->fresh()->is_active)->toBeFalse();
});

test('update prevents modifying another user job', function () {
    $otherUser = User::factory()->create();
    $job = ScheduledJob::factory()->create(['user_id' => $otherUser->id]);

    $this->patch("/system/scheduled-jobs/{$job->id}", [
        'title' => 'Hacked',
    ])->assertForbidden();
});

test('destroy soft deletes a scheduled job', function () {
    $job = ScheduledJob::factory()->create(['user_id' => $this->user->id]);

    $this->delete("/system/scheduled-jobs/{$job->id}")->assertRedirect();

    $this->assertSoftDeleted('scheduled_jobs', ['id' => $job->id]);
});

test('destroy prevents deleting another user job', function () {
    $otherUser = User::factory()->create();
    $job = ScheduledJob::factory()->create(['user_id' => $otherUser->id]);

    $this->delete("/system/scheduled-jobs/{$job->id}")->assertForbidden();
});

test('index requires authentication', function () {
    auth()->logout();

    $this->get('/system/scheduled-jobs')->assertRedirect('/login');
});

test('trigger dispatches ProcessScheduledJob', function () {
    Queue::fake([ProcessScheduledJob::class]);

    $job = ScheduledJob::factory()->create(['user_id' => $this->user->id]);

    $this->post("/system/scheduled-jobs/{$job->id}/trigger")
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(ProcessScheduledJob::class, fn ($queued) => $queued->scheduledJob->id === $job->id && $queued->isManualTrigger === true);
});

test('trigger prevents triggering another user job', function () {
    Queue::fake([ProcessScheduledJob::class]);

    $otherUser = User::factory()->create();
    $job = ScheduledJob::factory()->create(['user_id' => $otherUser->id]);

    $this->post("/system/scheduled-jobs/{$job->id}/trigger")->assertForbidden();

    Queue::assertNotPushed(ProcessScheduledJob::class);
});
