<?php

namespace App\Services\Views;

use App\Models\CpmRate;

class RateResolver
{
    private const FALLBACK_COUNTRY = 'XX';

    /**
     * Micro-USD earned by one view: the rate per 1,000 views for the country and level,
     * falling back to the "XX" row, divided by 1,000. Returns 0 when no rate exists.
     */
    public function ratePerViewMicro(string $country, int $level): int
    {
        $rates = CpmRate::query()
            ->where('level', $level)
            ->whereIn('country', [strtoupper($country), self::FALLBACK_COUNTRY])
            ->pluck('rate_per_1000_micro', 'country');

        $ratePer1000 = $rates->get(strtoupper($country)) ?? $rates->get(self::FALLBACK_COUNTRY);

        return intdiv((int) $ratePer1000, 1000);
    }
}
