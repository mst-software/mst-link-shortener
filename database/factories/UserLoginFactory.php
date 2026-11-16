<?php

namespace Database\Factories;

use App\Enums\LoginEvent;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLogin>
 */
class UserLoginFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userAgent = fake()->userAgent();

        return [
            'user_id' => User::factory(),
            'event' => LoginEvent::Login,
            'ip' => fake()->ipv4(),
            'ua_hash' => hash('sha256', $userAgent),
            'user_agent' => mb_substr($userAgent, 0, UserLogin::USER_AGENT_LENGTH),
            'country' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the footprint was recorded when the account was created.
     */
    public function signup(): static
    {
        return $this->state(fn (): array => ['event' => LoginEvent::Signup]);
    }

    /**
     * Indicate that the footprint came from the given browser user agent.
     */
    public function withUserAgent(string $userAgent): static
    {
        return $this->state(fn (): array => [
            'ua_hash' => hash('sha256', $userAgent),
            'user_agent' => mb_substr($userAgent, 0, UserLogin::USER_AGENT_LENGTH),
        ]);
    }
}
