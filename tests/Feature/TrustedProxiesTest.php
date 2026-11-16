<?php

use App\Support\TrustedProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

const PROXY_TEST_CLOUDFLARE_IP = '173.245.48.10';

beforeEach(function () {
    Route::get('testing/proxy-check', fn (Request $request): array => [
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
    ]);
});

/**
 * @param  array<string, string>  $headers
 */
function requestThroughProxy(string $proxies, string $peer, array $headers = []): TestResponse
{
    config(['trustedproxy.proxies' => $proxies]);

    return test()->withServerVariables(['REMOTE_ADDR' => $peer])->withHeaders($headers)->getJson('testing/proxy-check');
}

it('ignores forwarding headers when no proxy is trusted', function () {
    requestThroughProxy('', '203.0.113.5', ['X-Forwarded-For' => '198.51.100.7', 'X-Forwarded-Proto' => 'https'])
        ->assertJson(['ip' => '203.0.113.5', 'secure' => false]);
});

it('takes the visitor address and scheme from a trusted proxy', function () {
    requestThroughProxy('10.0.0.0/8', '10.1.2.3', ['X-Forwarded-For' => '198.51.100.7', 'X-Forwarded-Proto' => 'https'])
        ->assertJson(['ip' => '198.51.100.7', 'secure' => true]);
});

it('does not let an untrusted client spoof its address', function () {
    requestThroughProxy('10.0.0.0/8', '203.0.113.5', ['X-Forwarded-For' => '198.51.100.7'])
        ->assertJson(['ip' => '203.0.113.5']);
});

it('ignores addresses a client prepended before the trusted proxy', function () {
    requestThroughProxy('10.0.0.0/8', '10.1.2.3', ['X-Forwarded-For' => '1.1.1.1, 198.51.100.7'])
        ->assertJson(['ip' => '198.51.100.7']);
});

it('trusts whichever server connects with the wildcard', function () {
    requestThroughProxy('*', '192.0.2.44', ['X-Forwarded-For' => '198.51.100.7'])
        ->assertJson(['ip' => '198.51.100.7']);
});

it('takes the visitor address from CF-Connecting-IP of a Cloudflare edge', function () {
    requestThroughProxy('cloudflare', PROXY_TEST_CLOUDFLARE_IP, [
        'CF-Connecting-IP' => '198.51.100.7',
        'X-Forwarded-For' => '1.1.1.1, 198.51.100.7',
        'X-Forwarded-Proto' => 'https',
    ])->assertJson(['ip' => '198.51.100.7', 'secure' => true]);
});

it('ignores Cloudflare headers sent straight to the server', function () {
    requestThroughProxy('cloudflare', '203.0.113.5', [
        'CF-Connecting-IP' => '198.51.100.7',
        'X-Forwarded-For' => '198.51.100.7',
    ])->assertJson(['ip' => '203.0.113.5']);
});

it('falls back to X-Forwarded-For when a Cloudflare request has no valid CF-Connecting-IP', function () {
    requestThroughProxy('cloudflare', PROXY_TEST_CLOUDFLARE_IP, [
        'CF-Connecting-IP' => 'not-an-ip',
        'X-Forwarded-For' => '1.1.1.1, 198.51.100.7',
    ])->assertJson(['ip' => '198.51.100.7']);
});

it('drops a forwarded host passed through Cloudflare', function () {
    requestThroughProxy('cloudflare', PROXY_TEST_CLOUDFLARE_IP, ['X-Forwarded-Host' => 'evil.example'])
        ->assertJson(['host' => 'localhost']);
});

it('expands the cloudflare preset next to other entries', function () {
    config(['trustedproxy.proxies' => ' Cloudflare , 10.0.0.0/8 ,']);

    expect(TrustedProxies::proxies())->toContain('173.245.48.0/20', '2606:4700::/32', '10.0.0.0/8')
        ->and(TrustedProxies::trustsCloudflare())->toBeTrue();
});

it('trusts no proxy by default', function () {
    config(['trustedproxy.proxies' => null]);

    expect(TrustedProxies::proxies())->toBe([])
        ->and(TrustedProxies::trustsCloudflare())->toBeFalse();
});
