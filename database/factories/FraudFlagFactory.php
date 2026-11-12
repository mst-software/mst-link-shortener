<?php

namespace Database\Factories;

use App\Models\FraudFlag;
use App\Models\Link;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FraudFlag>
 */
class FraudFlagFactory extends Factory
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
            'user_id' => fn (array $attributes): int => Link::query()->findOrFail($attributes['link_id'])->user_id,
            'reason' => 'suspicious_traffic',
            'details' => ['note' => fake()->sentence()],
            'resolved_at' => null,
        ];
    }

    /**
     * Indicate that the flag has been resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => ['resolved_at' => now()]);
    }
}
