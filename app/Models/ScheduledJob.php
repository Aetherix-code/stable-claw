<?php

namespace App\Models;

use Database\Factories\ScheduledJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledJob extends Model
{
    /** @use HasFactory<ScheduledJobFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'title',
        'prompt',
        'source',
        'frequency',
        'respond_channel',
        'scheduled_at',
        'last_run_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'last_run_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isDue(): bool
    {
        return $this->is_active && $this->scheduled_at->lte(now());
    }

    public function calculateNextRun(): void
    {
        $this->last_run_at = now();

        if ($this->frequency === 'once') {
            $this->is_active = false;
        } else {
            $this->scheduled_at = match ($this->frequency) {
                'hourly' => $this->scheduled_at->addHour(),
                'daily' => $this->scheduled_at->addDay(),
                'weekly' => $this->scheduled_at->addWeek(),
                'monthly' => $this->scheduled_at->addMonth(),
            };
        }

        $this->save();
    }
}
