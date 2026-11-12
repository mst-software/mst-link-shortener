<?php

namespace Database\Factories;

use App\Models\DailyStat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyStat>
 */
class DailyStatFactory extends Factory
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
            'stat_date' => fake()->unique()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'views' => fake()->numberBetween(0, 500),
            'earn_micro' => fake()->numberBetween(0, 5_000_000),
            'ref_earn_micro' => 0,
        ];
    }
}
