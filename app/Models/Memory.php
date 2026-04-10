<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Memory extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'is_sensitive',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
        ];
    }

    public static function recall(string $key): ?string
    {
        $memory = static::where('key', $key)->first();

        if (! $memory) {
            return null;
        }

        return $memory->is_sensitive
            ? Crypt::decryptString($memory->value)
            : $memory->value;
    }

    public static function remember(string $key, string $value, string $type = 'fact', bool $sensitive = false, ?string $description = null): self
    {
        $stored = $sensitive ? Crypt::encryptString($value) : $value;

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'type' => $type,
                'is_sensitive' => $sensitive,
                'description' => $description,
            ]
        );
    }

    public function getDisplayValueAttribute(): string
    {
        return $this->is_sensitive ? '••••••••' : ($this->value ?? '');
    }
}
