<?php

namespace Database\Factories;

use App\Models\CpmRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CpmRate>
 */
class CpmRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country' => fake()->unique()->countryCode(),
            'level' => fake()->numberBetween(1, 3),
            'rate_per_1000_micro' => fake()->numberBetween(1_000_000, 20_000_000),
        ];
    }
}
