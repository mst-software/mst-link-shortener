<?php

namespace Database\Factories;

use App\Enums\ViewStatus;
use App\Models\Link;
use App\Models\View;
use App\Models\VisitSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<View>
 */
class ViewFactory extends Factory
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
            'visit_session_id' => fn (array $attributes): string => VisitSession::factory()
                ->create(['link_id' => $attributes['link_id']])
                ->getKey(),
            'ip' => fake()->ipv4(),
            'country' => 'XX',
            'level' => 1,
            'status' => ViewStatus::Pending,
            'invalid_reason' => null,
            'earn_micro' => 0,
            'matures_at' => now()->addDays(3),
            'aggregated_at' => null,
        ];
    }
}
