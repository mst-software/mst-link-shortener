<?php

namespace App\Support;

class DomainMasker
{
    /**
     * Hide the last three characters of the name part of a domain, so "example-advertiser.com"
     * becomes "example-adverti***.com". Domains without a dot or with a very short name are kept as they are.
     */
    public static function mask(string $domain): string
    {
        $dot = strrpos($domain, '.');

        if ($dot === false) {
            return $domain;
        }

        $name = substr($domain, 0, $dot);

        if (strlen($name) <= 3) {
            return $domain;
        }

        return substr($name, 0, -3).'***'.substr($domain, $dot);
    }
}
