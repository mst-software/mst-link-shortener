<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Reads the trusted proxy settings of config/trustedproxy.php (TRUSTED_PROXIES, TRUST_COUNTRY_HEADER).
 */
class TrustedProxies
{
    /**
     * The TRUSTED_PROXIES entry that expands to Cloudflare's published ranges.
     */
    public const CLOUDFLARE_PRESET = 'cloudflare';

    /**
     * The trusted proxies: "*" for whichever server connects, otherwise a list of IPs and CIDR ranges (empty when none).
     *
     * @return '*'|list<string>
     */
    public static function proxies(): string|array
    {
        $entries = self::entries();

        if (in_array('*', $entries, true) || in_array('**', $entries, true)) {
            return '*';
        }

        $proxies = [];

        foreach ($entries as $entry) {
            array_push($proxies, ...($entry === self::CLOUDFLARE_PRESET ? self::cloudflareRanges() : [$entry]));
        }

        return array_values(array_unique($proxies));
    }

    /**
     * Whether TRUSTED_PROXIES contains the "cloudflare" preset.
     */
    public static function trustsCloudflare(): bool
    {
        return in_array(self::CLOUDFLARE_PRESET, self::entries(), true);
    }

    /**
     * Whether the server that connected (not a forwarded address) is a Cloudflare edge and Cloudflare is trusted.
     */
    public static function isFromTrustedCloudflare(Request $request): bool
    {
        return self::trustsCloudflare() && self::peerIsCloudflare($request);
    }

    /**
     * Whether the CF-IPCountry header of this request may be believed: the header is trusted
     * (TRUST_COUNTRY_HEADER, on by default with the Cloudflare preset) and the request came from a Cloudflare edge.
     */
    public static function trustsCountryHeader(Request $request): bool
    {
        $setting = config('trustedproxy.trust_country_header');
        $isTrusted = $setting === null || $setting === '' ? self::trustsCloudflare() : filter_var($setting, FILTER_VALIDATE_BOOLEAN);

        return $isTrusted && self::peerIsCloudflare($request);
    }

    /**
     * @return list<string>
     */
    public static function cloudflareRanges(): array
    {
        return array_values((array) config('trustedproxy.cloudflare', []));
    }

    private static function peerIsCloudflare(Request $request): bool
    {
        $peer = (string) $request->server->get('REMOTE_ADDR', '');

        return $peer !== '' && IpUtils::checkIp($peer, self::cloudflareRanges());
    }

    /**
     * The non-empty TRUSTED_PROXIES entries, lower case.
     *
     * @return list<string>
     */
    private static function entries(): array
    {
        $configured = config('trustedproxy.proxies');
        $entries = is_array($configured) ? $configured : explode(',', (string) $configured);

        return array_values(array_filter(
            array_map(fn (mixed $entry): string => strtolower(trim((string) $entry)), $entries),
            fn (string $entry): bool => $entry !== '',
        ));
    }
}
