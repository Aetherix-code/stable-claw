<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TelegramSetting extends Model
{
    protected $fillable = [
        'bot_token',
        'allowed_usernames',
        'conversation_timeout_minutes',
    ];

    protected function casts(): array
    {
        return [
            'allowed_usernames' => 'array',
            'conversation_timeout_minutes' => 'integer',
        ];
    }

    public function setBotTokenAttribute(?string $value): void
    {
        $this->attributes['bot_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getBotTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Get the singleton settings instance, creating one if it doesn't exist.
     */
    public static function instance(): self
    {
        return static::firstOrCreate([], [
            'allowed_usernames' => [],
            'conversation_timeout_minutes' => 30,
        ]);
    }
}
