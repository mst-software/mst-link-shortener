<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    /**
     * Seed one demo campaign for the local fake advertiser site. Does nothing outside the local environment.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        Campaign::query()->firstOrCreate(
            ['widget_key' => 'demo-campaign-key-0001'],
            [
                'name' => 'Demo campaign',
                'keyword' => 'how to repot a houseplant',
                'domain' => 'localhost',
                'display_domain' => 'local***host',
                'is_active' => true,
                'weight' => 1,
                'daily_limit' => 0,
                'stage1_seconds' => 60,
                'stage2_seconds' => 15,
            ],
        );
    }
}
