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

    /**
     * Indicate that visitors must enter the given password.
     */
    public function passwordProtected(string $password = 'secret-pass'): static
    {
        return $this->state(fn (array $attributes) => ['password' => $password]);
    }

    /**
     * Indicate that the link expired a day ago.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subDay()]);
    }

    /**
     * Indicate that the link expires in a week.
     */
    public function expiresSoon(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->addWeek()]);
    }
}
