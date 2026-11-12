<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CodeAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CodeAttempt>
 */
class CodeAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'ip' => fake()->ipv4(),
            'ua_hash' => hash('sha256', fake()->userAgent()),
            'fingerprint' => null,
            'referrer_host' => null,
            'started_at' => now()->subMinutes(2),
            'progressed_at' => null,
            'page_load_ids' => [Str::random(16)],
            'code' => null,
            'issued_at' => null,
            'expires_at' => null,
            'used_at' => null,
            'visit_session_id' => null,
        ];
    }

    /**
     * Indicate that a code has been issued.
     */
    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'progressed_at' => now()->subMinute(),
            'code' => Str::lower(Str::random(6)),
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
