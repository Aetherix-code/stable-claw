<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledJob;
use App\Models\ScheduledJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('secretary:dispatch-scheduled-jobs')]
#[Description('Find due scheduled jobs and dispatch them to the queue.')]
class DispatchScheduledJobs extends Command
{
    public function handle(): int
    {
        $dueJobs = ScheduledJob::where('is_active', true)
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($dueJobs->isEmpty()) {
            $this->info('No scheduled jobs due.');

            return self::SUCCESS;
        }

        foreach ($dueJobs as $job) {
            ProcessScheduledJob::dispatch($job);
            $this->info("Dispatched: {$job->title} (#{$job->id})");
        }

        $this->info("Dispatched {$dueJobs->count()} job(s).");

        return self::SUCCESS;
    }
}
