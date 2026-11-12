<?php

namespace Database\Factories;

use App\Enums\AdDevice;
use App\Enums\AdPlacement;
use App\Models\AdSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdSlot>
 */
class AdSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'placement' => AdPlacement::Top,
            'device' => AdDevice::All,
            'network' => null,
            'code' => '<div class="demo-ad">'.fake()->unique()->bothify('ad-code-########').'</div>',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the slot is switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Place the slot at the given placement.
     */
    public function forPlacement(AdPlacement $placement): static
    {
        return $this->state(fn (array $attributes) => ['placement' => $placement]);
    }

    /**
     * Show the slot only on the given device class.
     */
    public function forDevice(AdDevice $device): static
    {
        return $this->state(fn (array $attributes) => ['device' => $device]);
    }
}
