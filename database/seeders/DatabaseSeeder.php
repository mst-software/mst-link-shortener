<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            CpmRateSeeder::class,
            AdminSeeder::class,
            AdSlotSeeder::class,
        ]);

        User::factory()->create([
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);
    }
}
