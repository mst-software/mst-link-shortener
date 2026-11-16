<?php

use App\Contracts\CountryResolver;
use App\Services\Views\HeaderCountryResolver;
use App\Services\Views\VisitorContext;
use Illuminate\Http\Request;

const CLOUDFLARE_EDGE_IP = '173.245.48.9';

beforeEach(function () {
    config(['trustedproxy.proxies' => 'cloudflare', 'trustedproxy.trust_country_header' => null]);
});

function visitorRequest(array $headers = [], string $ip = CLOUDFLARE_EDGE_IP): Request
{
    $server = ['REMOTE_ADDR' => $ip];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return Request::create('/', 'GET', [], [], [], $server);
}

it('binds the country resolver to the header implementation', function () {
    expect(app(CountryResolver::class))->toBeInstanceOf(HeaderCountryResolver::class);
});

it('reads the country from the Cloudflare header of a Cloudflare request in upper case', function () {
    $context = VisitorContext::fromRequest(
        visitorRequest(['CF-IPCountry' => 'vn', 'User-Agent' => 'Mozilla/5.0']),
        app(CountryResolver::class),
    );

    expect($context->country)->toBe('VN')
        ->and($context->ip)->toBe(CLOUDFLARE_EDGE_IP)
        ->and($context->userAgent)->toBe('Mozilla/5.0')
        ->and($context->uaHash)->toBe(hash('sha256', 'Mozilla/5.0'));
});

it('uses XX when the header is missing or not a country code', function (array $headers) {
    $context = VisitorContext::fromRequest(visitorRequest($headers), app(CountryResolver::class));

    expect($context->country)->toBe('XX');
})->with([
    'missing' => [[]],
    'unknown' => [['CF-IPCountry' => 'XX']],
    'tor' => [['CF-IPCountry' => 'T1']],
    'garbage' => [['CF-IPCountry' => 'Viet Nam']],
]);

it('takes the address from the request and ignores client supplied forwarding headers', function () {
    $context = VisitorContext::fromRequest(
        visitorRequest(['X-Forwarded-For' => '1.2.3.4'], '203.0.113.9'),
        app(CountryResolver::class),
    );

    expect($context->ip)->toBe('203.0.113.9');
});

it('ignores the country header of a request that does not come from Cloudflare', function () {
    $context = VisitorContext::fromRequest(visitorRequest(['CF-IPCountry' => 'US'], '203.0.113.9'), app(CountryResolver::class));

    expect($context->country)->toBe('XX');
});

it('ignores the country header while Cloudflare is not a trusted proxy', function (string $proxies) {
    config(['trustedproxy.proxies' => $proxies]);

    $context = VisitorContext::fromRequest(visitorRequest(['CF-IPCountry' => 'US']), app(CountryResolver::class));

    expect($context->country)->toBe('XX');
})->with(['no proxy' => '', 'any proxy' => '*', 'a private range' => '10.0.0.0/8']);

it('ignores the country header when it is switched off', function () {
    config(['trustedproxy.trust_country_header' => false]);

    $context = VisitorContext::fromRequest(visitorRequest(['CF-IPCountry' => 'US']), app(CountryResolver::class));

    expect($context->country)->toBe('XX');
});

it('reads the country header of a Cloudflare request when it is switched on without the preset', function () {
    config(['trustedproxy.proxies' => '*', 'trustedproxy.trust_country_header' => true]);

    expect(VisitorContext::fromRequest(visitorRequest(['CF-IPCountry' => 'US']), app(CountryResolver::class))->country)->toBe('US')
        ->and(VisitorContext::fromRequest(visitorRequest(['CF-IPCountry' => 'US'], '203.0.113.9'), app(CountryResolver::class))->country)->toBe('XX');
});
