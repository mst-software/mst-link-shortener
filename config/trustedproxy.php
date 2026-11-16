<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies whose X-Forwarded-* headers are believed, read by
    | App\Http\Middleware\TrustProxies. The visitor IP drives view dedupe, the
    | IP change rule, blocked networks, throttling and audit logs, so only list
    | proxies that overwrite these headers.
    |
    | Empty (the default) trusts no proxy: the TCP peer is the visitor.
    | "*" trusts whichever server connects (only when the app is reachable
    | through that proxy alone). Otherwise a comma-separated list of IPs and
    | CIDR ranges, where "cloudflare" expands to the Cloudflare ranges below,
    | for example "cloudflare" or "10.0.0.0/8". Cloudflare in front of another
    | proxy is not supported: the edge is then not the connecting server, so
    | CF-Connecting-IP and CF-IPCountry are ignored (app:preflight warns).
    |
    */

    'proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | Trust the CF-IPCountry header
    |--------------------------------------------------------------------------
    |
    | The visitor country (and so the CPM rate) comes from Cloudflare's
    | CF-IPCountry header. It is only read when this is on and the request
    | really comes from a Cloudflare range; otherwise the country is "XX".
    | Unset, it is on exactly when TRUSTED_PROXIES contains "cloudflare".
    |
    */

    'trust_country_header' => env('TRUST_COUNTRY_HEADER'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare ranges
    |--------------------------------------------------------------------------
    |
    | Cloudflare's published edge ranges, used by the "cloudflare" preset.
    | They change from time to time and were bundled without a live check:
    | compare them with https://www.cloudflare.com/ips/ before going live and
    | refresh them whenever Cloudflare announces new ranges.
    |
    */

    'cloudflare' => [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ],

];
