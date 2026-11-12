<?php

namespace App\Services\Views;

use App\Contracts\CountryResolver;

/**
 * Reads the country from the Cloudflare "CF-IPCountry" header.
 */
class HeaderCountryResolver implements CountryResolver
{
    public function resolve(string $ip, ?string $cfCountryHeader): string
    {
        $country = strtoupper(trim((string) $cfCountryHeader));

        // Cloudflare also sends "XX" (unknown) and "T1" (Tor); neither is a real country.
        if (preg_match('/\A[A-Z]{2}\z/', $country) !== 1) {
            return 'XX';
        }

        return $country;
    }
}
