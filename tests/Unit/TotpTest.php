<?php

use App\Services\Totp;

/**
 * The RFC 6238 SHA1 seed "12345678901234567890" encoded as base32.
 */
const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

it('matches the RFC 6238 SHA1 test vectors', function (int $timestamp, string $expected): void {
    expect((new Totp)->codeAt(RFC_SECRET, $timestamp, 8))->toBe($expected);
})->with([
    [59, '94287082'],
    [1111111109, '07081804'],
    [1111111111, '14050471'],
    [1234567890, '89005924'],
    [2000000000, '69279037'],
    [20000000000, '65353130'],
]);

it('accepts a code from the previous, current and next time step', function (int $offset): void {
    $totp = new Totp;
    $now = 1234567890;
    $code = $totp->codeAt(RFC_SECRET, $now + $offset * Totp::PERIOD_SECONDS);

    expect($totp->verify(RFC_SECRET, $code, $now))->toBe($totp->timeStep($now) + $offset);
})->with([-1, 0, 1]);

it('rejects a code outside the one step window', function (): void {
    $totp = new Totp;
    $now = 1234567890;
    $code = $totp->codeAt(RFC_SECRET, $now + 2 * Totp::PERIOD_SECONDS);

    expect($totp->verify(RFC_SECRET, $code, $now))->toBeNull();
});

it('rejects malformed codes', function (string $code): void {
    expect((new Totp)->verify(RFC_SECRET, $code, 1234567890))->toBeNull();
})->with(['', '12345', '1234567', 'abcdef']);

it('generates a base32 secret that verifies its own codes', function (): void {
    $totp = new Totp;
    $secret = $totp->generateSecret();

    expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and($totp->verify($secret, $totp->codeAt($secret, 1700000000), 1700000000))->not->toBeNull();
});

it('builds an otpauth uri for authenticator apps', function (): void {
    expect((new Totp)->provisioningUri('ABC234', 'jane@example.com', 'LinkCash'))
        ->toBe('otpauth://totp/LinkCash:jane%40example.com?secret=ABC234&issuer=LinkCash&algorithm=SHA1&digits=6&period=30');
});
