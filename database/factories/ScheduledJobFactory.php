<?php

namespace Database\Factories;

use App\Models\ScheduledJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledJob>
 */
class ScheduledJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'conversation_id' => null,
            'title' => fake()->sentence(3),
            'prompt' => fake()->sentence(),
            'source' => 'manual',
            'frequency' => 'once',
            'respond_channel' => 'web',
            'scheduled_at' => now()->addHour(),
            'last_run_at' => null,
            'is_active' => true,
        ];
    }

    public function agent(): static
    {
        return $this->state(fn () => ['source' => 'agent']);
    }

    public function recurring(string $frequency = 'daily'): static
    {
        return $this->state(fn () => ['frequency' => $frequency]);
    }
}
