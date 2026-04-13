<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessScheduledJob;
use App\Models\ScheduledJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledJobController extends Controller
{
    public function index(Request $request): Response
    {
        $mapJob = fn (ScheduledJob $job) => [
            'id' => $job->id,
            'title' => $job->title,
            'prompt' => $job->prompt,
            'source' => $job->source,
            'frequency' => $job->frequency,
            'respond_channel' => $job->respond_channel,
            'scheduled_at' => $job->scheduled_at->toIso8601String(),
            'last_run_at' => $job->last_run_at?->toIso8601String(),
            'is_active' => $job->is_active,
            'created_at' => $job->created_at->diffForHumans(),
        ];

        $allJobs = $request->user()->scheduledJobs()
            ->orderByDesc('created_at')
            ->get();

        $active = $allJobs->filter(fn (ScheduledJob $job) => ! ($job->frequency === 'once' && ! $job->is_active && $job->last_run_at))
            ->values()
            ->map($mapJob);

        $archived = $allJobs->filter(fn (ScheduledJob $job) => $job->frequency === 'once' && ! $job->is_active && $job->last_run_at)
            ->values()
            ->map($mapJob);

        return Inertia::render('scheduled-jobs/Index', [
            'jobs' => $active,
            'archivedJobs' => $archived,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'prompt' => ['required', 'string', 'max:5000'],
            'frequency' => ['required', 'string', Rule::in(['once', 'hourly', 'daily', 'weekly', 'monthly'])],
            'respond_channel' => ['required', 'string', Rule::in(['web', 'telegram'])],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $request->user()->scheduledJobs()->create([
            ...$validated,
            'source' => 'manual',
        ]);

        return back()->with('success', 'Scheduled job created.');
    }

    public function update(Request $request, ScheduledJob $scheduledJob): RedirectResponse
    {
        if ($scheduledJob->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'prompt' => ['sometimes', 'string', 'max:5000'],
            'frequency' => ['sometimes', 'string', Rule::in(['once', 'hourly', 'daily', 'weekly', 'monthly'])],
            'respond_channel' => ['sometimes', 'string', Rule::in(['web', 'telegram'])],
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $scheduledJob->update($validated);

        return back()->with('success', 'Scheduled job updated.');
    }

    public function destroy(Request $request, ScheduledJob $scheduledJob): RedirectResponse
    {
        if ($scheduledJob->user_id !== $request->user()->id) {
            abort(403);
        }

        $scheduledJob->delete();

        return back()->with('success', 'Scheduled job deleted.');
    }

    public function trigger(Request $request, ScheduledJob $scheduledJob): RedirectResponse
    {
        if ($scheduledJob->user_id !== $request->user()->id) {
            abort(403);
        }

        ProcessScheduledJob::dispatch($scheduledJob, isManualTrigger: true);

        return back()->with('success', 'Job triggered — processing now.');
    }
}
