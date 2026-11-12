<?php

namespace Database\Factories;

use App\Enums\TransactionAction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1_000, 5_000_000);

        return [
            'user_id' => User::factory(),
            'type' => 'in',
            'action' => TransactionAction::ViewEarnings,
            'amount_micro' => $amount,
            'balance_before_micro' => 0,
            'balance_after_micro' => $amount,
            'reference_id' => null,
            'description' => '',
        ];
    }
}
