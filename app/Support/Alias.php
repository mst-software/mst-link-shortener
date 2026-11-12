<?php

namespace App\Support;

class Alias
{
    public const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 32;

    /**
     * Aliases that would collide with application routes or static files.
     *
     * @var list<string>
     */
    public const RESERVED = [
        'auth', 'member', 'admin', 'api', 'api-shorten', 'links', 'st', 'ref', 'pages', 'go',
        'js', 'css', 'assets', 'build', 'images', 'storage', 'blog', 'payout-rates',
        'favicon.ico', 'robots.txt', 'sitemap.xml',
    ];

    /**
     * Whether a member-chosen alias has an allowed shape (3 to 32 of A-Z a-z 0-9 _ -).
     */
    public static function isValid(string $alias): bool
    {
        return preg_match('/\A[A-Za-z0-9_-]{'.self::MIN_LENGTH.','.self::MAX_LENGTH.'}\z/', $alias) === 1;
    }

    /**
     * Whether an alias is reserved. Matching is case-insensitive so "Admin" cannot shadow "/admin".
     */
    public static function isReserved(string $alias): bool
    {
        return in_array(strtolower($alias), self::RESERVED, true);
    }

    /**
     * Generate a random Base62 alias using a cryptographically secure generator.
     * Never returns a reserved alias.
     */
    public static function generate(int $length = 6): string
    {
        $length = max(1, $length);

        do {
            $alias = '';

            for ($i = 0; $i < $length; $i++) {
                $alias .= self::ALPHABET[random_int(0, 61)];
            }
        } while (self::isReserved($alias));

        return $alias;
    }
}
