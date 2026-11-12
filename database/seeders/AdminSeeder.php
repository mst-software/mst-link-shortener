<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Create a demo administrator, only in the local environment.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        if (User::query()->where('username', 'admin')->exists()) {
            return;
        }

        User::factory()->admin()->create([
            'username' => 'admin',
            'email' => 'admin@example.com',
        ]);
    }
}
