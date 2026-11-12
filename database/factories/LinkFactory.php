<?php

namespace Database\Factories;

use App\Enums\LinkStatus;
use App\Models\Link;
use App\Models\User;
use App\Support\Alias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
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
            'alias' => Alias::generate(8),
            'url' => fake()->url(),
            'level' => 1,
            'status' => LinkStatus::Active,
        ];
    }

    /**
     * Indicate that the link is hidden by its owner.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => LinkStatus::Hidden]);
    }

    /**
     * Indicate that the link is banned.
     */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => ['status' => LinkStatus::Banned]);
    }
}
