<?php

namespace Database\Seeders;

use App\Enums\AdPlacement;
use App\Models\AdSlot;
use Illuminate\Database\Seeder;

class AdSlotSeeder extends Seeder
{
    /**
     * Seed one switched-off example slot so the admin has a starting point. Real ad code is added in the admin.
     */
    public function run(): void
    {
        AdSlot::query()->firstOrCreate(
            ['name' => 'Example banner'],
            [
                'placement' => AdPlacement::Top,
                'code' => '<!-- Paste your ad network code here -->',
                'is_active' => false,
            ],
        );
    }
}
