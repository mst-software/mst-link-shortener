<?php

namespace Database\Factories;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrustedDevice>
 */
class TrustedDeviceFactory extends Factory
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
            'token_hash' => hash('sha256', Str::random(64)),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'expires_at' => now()->addDays(30),
        ];
    }

    /**
     * Indicate that the device is trusted through the given plain cookie token.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (): array => ['token_hash' => hash('sha256', $token)]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
