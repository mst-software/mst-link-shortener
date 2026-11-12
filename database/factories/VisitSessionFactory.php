<?php

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Models\Link;
use App\Models\VisitSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitSession>
 */
class VisitSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_id' => Link::factory(),
            'ip' => fake()->ipv4(),
            'ua_hash' => hash('sha256', fake()->userAgent()),
            'fingerprint' => null,
            'country' => 'XX',
            'level' => 1,
            'current_step' => 1,
            'step_issued_at' => null,
            'token_hash' => null,
            'status' => VisitStatus::InProgress,
            'expires_at' => now()->addMinutes(30),
            'finished_at' => null,
        ];
    }
}
