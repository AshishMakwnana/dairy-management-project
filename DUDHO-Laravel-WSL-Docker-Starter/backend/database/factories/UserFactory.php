<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'mobile_country_code' => '+91',
            'mobile' => fake()->unique()->numerify('9#########'),
            'mobile_verified_at' => now(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'preferred_language' => 'en',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'mobile_verified_at' => null,
        ]);
    }
}
