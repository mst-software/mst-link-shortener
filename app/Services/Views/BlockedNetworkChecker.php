<?php

namespace App\Services\Views;

use App\Models\BlockedNetwork;
use Illuminate\Support\Facades\Cache;

class BlockedNetworkChecker
{
    private const CACHE_KEY = 'blocked_networks.cidrs';

    private const CACHE_TTL_SECONDS = 600;

    /**
     * Whether the IPv4 or IPv6 address falls inside any blocked CIDR range.
     */
    public function isBlocked(string $ip): bool
    {
        $address = inet_pton($ip);

        if ($address === false) {
            return false;
        }

        foreach ($this->cidrs() as $cidr) {
            if ($this->matches($address, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop the cached list, e.g. after an admin edits the blocked networks.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<string>
     */
    private function cidrs(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => BlockedNetwork::query()->pluck('cidr')->all(),
        );
    }

    /**
     * @param  string  $address  Packed binary address from inet_pton().
     */
    private function matches(string $address, string $cidr): bool
    {
        [$subnet, $prefix] = array_pad(explode('/', trim($cidr), 2), 2, null);

        $network = inet_pton((string) $subnet);

        // Address families must match (4 bytes for IPv4, 16 for IPv6).
        if ($network === false || strlen($network) !== strlen($address)) {
            return false;
        }

        $maxBits = strlen($address) * 8;

        if ($prefix === null) {
            $bits = $maxBits;
        } elseif (ctype_digit($prefix) && (int) $prefix <= $maxBits) {
            $bits = (int) $prefix;
        } else {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if (substr($address, 0, $fullBytes) !== substr($network, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($address[$fullBytes]) & $mask) === (ord($network[$fullBytes]) & $mask);
    }
}
