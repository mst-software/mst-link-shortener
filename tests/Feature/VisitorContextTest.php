<?php

use App\Contracts\CountryResolver;
use App\Services\Views\HeaderCountryResolver;
use App\Services\Views\VisitorContext;
use Illuminate\Http\Request;

function visitorRequest(array $headers = [], string $ip = '203.0.113.9'): Request
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

it('reads the country from the Cloudflare header in upper case', function () {
    $context = VisitorContext::fromRequest(
        visitorRequest(['CF-IPCountry' => 'vn', 'User-Agent' => 'Mozilla/5.0']),
        app(CountryResolver::class),
    );

    expect($context->country)->toBe('VN')
        ->and($context->ip)->toBe('203.0.113.9')
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
        visitorRequest(['X-Forwarded-For' => '1.2.3.4']),
        app(CountryResolver::class),
    );

    expect($context->ip)->toBe('203.0.113.9');
});
