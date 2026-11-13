<?php

namespace App\Services\Advertiser;

use App\Models\CpmRate;
use App\Support\Money;

/**
 * Warns an admin when an advertiser price would lose money on some publisher rate.
 */
class PriceGuard
{
    /**
     * The most a publisher can earn from one view, in micro-USD (highest country/level rate per 1,000, divided by 1,000).
     */
    public function highestPublisherPayoutMicro(): int
    {
        return intdiv((int) CpmRate::query()->max('rate_per_1000_micro'), 1000);
    }

    /**
     * A warning text when the price per view is below the highest publisher payout, otherwise null.
     */
    public function warningFor(int $priceMicro): ?string
    {
        $highest = $this->highestPublisherPayoutMicro();

        if ($priceMicro >= $highest) {
            return null;
        }

        return 'The price of '.Money::format($priceMicro).' per view is lower than the highest publisher payout of '
            .Money::format($highest).' per view, so some views will lose money.';
    }
}
