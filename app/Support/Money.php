<?php

namespace App\Support;

class Money
{
    public const MICRO_PER_USD = 1_000_000;

    /**
     * Convert a USD amount to micro-USD (1 USD = 1,000,000 micro-USD).
     */
    public static function toMicro(string|int|float $usd): int
    {
        return (int) round((float) $usd * self::MICRO_PER_USD);
    }

    /**
     * Format micro-USD as a dollar string, for example "$0.050".
     */
    public static function format(int $micro, int $decimals = 3): string
    {
        return '$'.number_format($micro / self::MICRO_PER_USD, $decimals, '.', '');
    }
}
