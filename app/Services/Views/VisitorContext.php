<?php

namespace App\Services\Views;

use App\Contracts\CountryResolver;
use App\Support\TrustedProxies;
use Illuminate\Http\Request;

final readonly class VisitorContext
{
    public function __construct(
        public string $ip,
        public string $uaHash,
        public string $country,
        public string $userAgent,
    ) {}

    /**
     * Build the context from a request. Only Request::ip() is trusted for the address (it honours
     * forwarding headers of TRUSTED_PROXIES only). The CF-IPCountry header is passed to the resolver
     * only when the request came from a trusted Cloudflare edge, so a direct client cannot pick its country.
     */
    public static function fromRequest(Request $request, CountryResolver $countries): self
    {
        $ip = (string) ($request->ip() ?? '0.0.0.0');
        $userAgent = (string) $request->userAgent();

        return new self(
            ip: $ip,
            uaHash: hash('sha256', $userAgent),
            country: $countries->resolve($ip, TrustedProxies::trustsCountryHeader($request) ? $request->header('CF-IPCountry') : null),
            userAgent: $userAgent,
        );
    }
}
