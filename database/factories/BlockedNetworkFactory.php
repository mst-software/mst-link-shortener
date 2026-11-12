<?php

namespace Database\Factories;

use App\Models\BlockedNetwork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedNetwork>
 */
class BlockedNetworkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cidr' => fake()->numberBetween(1, 223).'.'.fake()->numberBetween(0, 255).'.0.0/16',
            'label' => fake()->optional()->company(),
        ];
    }
}
