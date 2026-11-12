<?php

namespace Database\Factories;

use App\Models\ViewDedup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViewDedup>
 */
class ViewDedupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key_hash' => hash('sha256', fake()->unique()->uuid()),
            'expires_at' => now()->addHours(24),
        ];
    }
}
