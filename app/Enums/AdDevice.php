<?php

namespace App\Enums;

enum AdDevice: string
{
    case All = 'all';
    case Desktop = 'desktop';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All devices',
            self::Desktop => 'Desktop only',
            self::Mobile => 'Mobile only',
        };
    }

    /**
     * Detect the visitor's device class from the User-Agent header.
     */
    public static function fromUserAgent(?string $userAgent): self
    {
        return preg_match('/Mobi|Android|iPhone|iPad|iPod|Windows Phone|BlackBerry|Opera Mini/i', (string) $userAgent) === 1
            ? self::Mobile
            : self::Desktop;
    }
}
