<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
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
            'type' => 'mail',
            'provider' => 'gmail',
            'name' => 'My Gmail',
            'credentials' => [
                'email' => fake()->safeEmail(),
                'password' => fake()->password(),
            ],
            'is_active' => true,
        ];
    }
}
