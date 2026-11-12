<?php

namespace Database\Factories;

use App\Enums\PayoutMethod;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
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
            'amount_micro' => 10_000_000,
            'method' => PayoutMethod::Paypal,
            'details' => ['paypal_email' => fake()->safeEmail()],
            'traffic_source' => fake()->url(),
            'status' => WithdrawalStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['status' => WithdrawalStatus::Paid, 'processed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => WithdrawalStatus::Rejected,
            'admin_note' => 'Rejected in test.',
            'processed_at' => now(),
        ]);
    }
}
