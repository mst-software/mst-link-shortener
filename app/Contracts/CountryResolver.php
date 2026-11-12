<?php

namespace App\Contracts;

interface CountryResolver
{
    /**
     * Resolve the visitor's ISO 3166-1 alpha-2 country code, upper case.
     * Returns "XX" when the country is unknown.
     */
    public function resolve(string $ip, ?string $cfCountryHeader): string;
}
