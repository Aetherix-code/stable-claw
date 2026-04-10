<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Skill extends Model
{
    protected $fillable = [
        'name',
        'description',
        'detailed_instructions',
        'trigger_keywords',
        'steps',
        'transcript',
        'memory_keys',
        'learned_from_conversation_id',
    ];

    protected function casts(): array
    {
        return [
            'trigger_keywords' => 'array',
            'steps' => 'array',
            'memory_keys' => 'array',
        ];
    }

    public function learnedFromConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'learned_from_conversation_id');
    }

    public function matchesTrigger(string $input): bool
    {
        $keywords = $this->trigger_keywords ?? [];
        $inputLower = mb_strtolower($input);

        foreach ($keywords as $keyword) {
            if (str_contains($inputLower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}
