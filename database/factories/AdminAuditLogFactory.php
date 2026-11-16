<?php

namespace Database\Factories;

use App\Enums\AdminAction;
use App\Enums\UserStatus;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => User::factory()->admin(),
            'target_user_id' => User::factory(),
            'action' => AdminAction::UserBanned,
            'reason' => fake()->sentence(),
            'old_values' => ['status' => UserStatus::Active->value],
            'new_values' => ['status' => UserStatus::Banned->value],
        ];
    }
}
