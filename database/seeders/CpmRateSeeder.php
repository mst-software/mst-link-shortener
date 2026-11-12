<?php

namespace Database\Seeders;

use App\Models\CpmRate;
use Illuminate\Database\Seeder;

class CpmRateSeeder extends Seeder
{
    /**
     * Sample publisher rates in USD per 1,000 views for levels 1, 2 and 3.
     * "XX" is the fallback row for countries without their own rate.
     *
     * @var array<string, array{float, float, float}>
     */
    private const RATES = [
        'VN' => [4.50, 6.00, 7.50],
        'US' => [12.00, 16.00, 20.00],
        'GB' => [10.00, 13.00, 16.00],
        'CA' => [9.00, 12.00, 15.00],
        'AU' => [9.00, 12.00, 14.00],
        'DE' => [8.00, 11.00, 13.00],
        'FR' => [7.00, 9.00, 11.00],
        'JP' => [6.00, 8.00, 10.00],
        'TH' => [3.50, 4.50, 5.50],
        'PH' => [2.50, 3.50, 4.50],
        'ID' => [3.00, 4.00, 5.00],
        'IN' => [2.00, 3.00, 4.00],
        'XX' => [1.50, 2.00, 2.50],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::RATES as $country => $rates) {
            foreach ($rates as $index => $usdPer1000) {
                CpmRate::query()->updateOrCreate(
                    ['country' => $country, 'level' => $index + 1],
                    ['rate_per_1000_micro' => (int) round($usdPer1000 * 1_000_000)],
                );
            }
        }
    }
}
