<?php

namespace Database\Factories;

use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Models\TopUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TopUp>
 */
class TopUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->advertiser(),
            'provider' => TopUpProvider::Sepay,
            'reference' => 'LC'.strtoupper(Str::random(8)),
            'status' => TopUpStatus::Pending,
            'amount_micro' => 10_000_000,
            'original_amount' => 250_000,
            'original_currency' => 'VND',
            'exchange_rate' => 25_000,
            'expires_at' => now()->addDay(),
        ];
    }
}
