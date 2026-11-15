<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'keyword' => fake()->words(4, true),
            'domain' => fake()->unique()->domainName(),
            'display_domain' => 'exa***.com',
            'widget_key' => Str::random(24),
            'is_active' => true,
            'weight' => 1,
            'daily_limit' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'stage1_seconds' => 60,
            'stage2_seconds' => 15,
            'image_google' => null,
            'image_button' => null,
            'image_position' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the campaign belongs to an advertiser.
     */
    public function forAdvertiser(?User $owner = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $owner?->getKey() ?? User::factory()->advertiser(),
        ]);
    }

    /**
     * Indicate that the campaign waits for admin review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['review_status' => ReviewStatus::Pending]);
    }

    /**
     * Indicate that the campaign is switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Indicate that the campaign has a daily limit of one code and has already issued it today.
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => ['daily_limit' => 1])
            ->afterCreating(function (Campaign $campaign): void {
                $campaign->attempts()->create([
                    'ip' => fake()->ipv4(),
                    'ua_hash' => hash('sha256', fake()->userAgent()),
                    'started_at' => now()->subMinutes(5),
                    'page_load_ids' => [],
                    'code' => 'abcdef',
                    'issued_at' => now(),
                    'expires_at' => now()->addMinutes(10),
                ]);
            });
    }
}
