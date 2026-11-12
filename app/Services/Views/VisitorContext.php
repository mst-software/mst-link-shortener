<?php

namespace App\Services\Views;

use App\Contracts\CountryResolver;
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
     * Build the context from a request. Only Request::ip() is trusted for the address
     * (the app trusts Cloudflare proxies); the country comes from the resolver.
     */
    public static function fromRequest(Request $request, CountryResolver $countries): self
    {
        $ip = (string) ($request->ip() ?? '0.0.0.0');
        $userAgent = (string) $request->userAgent();

        return new self(
            ip: $ip,
            uaHash: hash('sha256', $userAgent),
            country: $countries->resolve($ip, $request->header('CF-IPCountry')),
            userAgent: $userAgent,
        );
    }
}
