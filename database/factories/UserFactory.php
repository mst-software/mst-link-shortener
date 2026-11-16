<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * The plain recovery codes stored (hashed) by the withTwoFactor() state.
     *
     * @var list<string>
     */
    public const RECOVERY_CODES = ['aaaaa-11111', 'bbbbb-22222'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that the user is an advertiser.
     */
    public function advertiser(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Advertiser,
            'terms_accepted_at' => now(),
        ]);
    }

    /**
     * Indicate that the user has two-factor authentication turned on.
     * The recovery codes are UserFactory::RECOVERY_CODES.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => (new Totp)->generateSecret(),
            'two_factor_recovery_codes' => array_map(fn (string $code): string => Hash::make($code), self::RECOVERY_CODES),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user started two-factor setup but has not confirmed a code yet.
     */
    public function withPendingTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => (new Totp)->generateSecret(),
        ]);
    }

    /**
     * Indicate that the user is banned.
     */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Banned,
        ]);
    }
}
