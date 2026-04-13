<?php

use App\Jobs\ProcessScheduledJob;
use App\Models\ScheduledJob;
use Illuminate\Support\Facades\Queue;

test('dispatches due jobs to the queue', function () {
    Queue::fake();

    $dueJob = ScheduledJob::factory()->create([
        'is_active' => true,
        'scheduled_at' => now()->subMinute(),
    ]);

    $futureJob = ScheduledJob::factory()->create([
        'is_active' => true,
        'scheduled_at' => now()->addHour(),
    ]);

    $this->artisan('secretary:dispatch-scheduled-jobs')
        ->assertSuccessful();

    Queue::assertPushed(ProcessScheduledJob::class, 1);
    Queue::assertPushed(ProcessScheduledJob::class, fn ($job) => $job->scheduledJob->id === $dueJob->id);
});

test('skips inactive jobs', function () {
    Queue::fake();

    ScheduledJob::factory()->create([
        'is_active' => false,
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('secretary:dispatch-scheduled-jobs')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('outputs message when no jobs are due', function () {
    Queue::fake();

    $this->artisan('secretary:dispatch-scheduled-jobs')
        ->expectsOutputToContain('No scheduled jobs due')
        ->assertSuccessful();
});
