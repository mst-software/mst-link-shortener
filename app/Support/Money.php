<?php

namespace App\Support;

class Money
{
    public const MICRO_PER_USD = 1_000_000;

    /**
     * Convert a USD amount to micro-USD (1 USD = 1,000,000 micro-USD).
     *
     * Plain decimal strings (what forms send) are converted digit by digit, so no float rounding
     * is involved; digits after the sixth decimal are rounded half up. Floats are first written
     * with six decimals, and other numeric strings (such as "1e3") fall back to that float path.
     */
    public static function toMicro(string|int|float $usd): int
    {
        if (is_int($usd)) {
            return $usd * self::MICRO_PER_USD;
        }

        if (is_float($usd)) {
            $usd = sprintf('%.6F', $usd);
        }

        $usd = trim($usd);

        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $usd, $parts) !== 1 || ($parts[2] === '' && ($parts[3] ?? '') === '')) {
            return is_numeric($usd) ? self::toMicro((float) $usd) : 0;
        }

        $fraction = str_pad($parts[3] ?? '', 7, '0');
        $micro = (int) $parts[2] * self::MICRO_PER_USD + (int) substr($fraction, 0, 6);

        if ((int) $fraction[6] >= 5) {
            $micro++;
        }

        return $parts[1] === '-' ? -$micro : $micro;
    }

    /**
     * Micro-USD as a plain USD number for form fields, without trailing zeros, for example "7.5".
     */
    public static function toUsdString(int $micro): string
    {
        return rtrim(rtrim(number_format($micro / self::MICRO_PER_USD, 6, '.', ''), '0'), '.');
    }

    /**
     * Format micro-USD as a dollar string, for example "$0.050".
     */
    public static function format(int $micro, int $decimals = 3): string
    {
        return '$'.number_format($micro / self::MICRO_PER_USD, $decimals, '.', '');
    }
}
