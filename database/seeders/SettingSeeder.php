<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (config('linkcash.defaults') as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }
}
