<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'telegram_chat_id',
        'title',
        'is_learn_mode',
        'learn_mode_skill_name',
        'learn_mode_started_at',
        'ai_provider',
        'is_processing',
        'skill_id',
        'scheduled_job_id',
    ];

    protected function casts(): array
    {
        return [
            'is_learn_mode' => 'boolean',
            'is_processing' => 'boolean',
            'learn_mode_started_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledJob(): BelongsTo
    {
        return $this->belongsTo(ScheduledJob::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SecretaryMessage::class)->orderBy('created_at');
    }

    public function toolExecutions(): HasMany
    {
        return $this->hasMany(ToolExecution::class);
    }

    public function startLearnMode(string $skillName): void
    {
        $this->update([
            'is_learn_mode' => true,
            'learn_mode_skill_name' => $skillName,
            'learn_mode_started_at' => CarbonImmutable::now(),
        ]);
    }

    public function stopLearnMode(): void
    {
        $this->update(['is_learn_mode' => false]);
    }
}
