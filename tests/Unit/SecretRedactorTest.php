<?php

use App\Support\SecretRedactor;

it('replaces every occurrence of a known secret of any length', function () {
    expect(SecretRedactor::redact('Login with "hunter2" failed, hunter2 rejected', ['hunter2']))
        ->toBe('Login with "********" failed, ******** rejected')
        ->and(SecretRedactor::redact('code ab was wrong', ['ab']))->toBe('code ******** was wrong');
});

it('replaces the longest secret first and the url-encoded form', function () {
    expect(SecretRedactor::redact('key AKIA, secret AKIA-long/secret+x, sent AKIA-long%2Fsecret%2Bx', ['AKIA', 'AKIA-long/secret+x']))
        ->toBe('key ********, secret ********, sent ********');
});

it('ignores empty secrets', function () {
    expect(SecretRedactor::redact('Connection refused', ['', null]))->toBe('Connection refused');
});

it('redacts credentials in urls', function (string $message, string $expected) {
    expect(SecretRedactor::redact($message))->toBe($expected);
})->with([
    'user and password' => ['Cannot reach smtp://mailer:p4ss@smtp.example.com:587', 'Cannot reach smtp://********@smtp.example.com:587'],
    'token only' => ['GET https://abc123@api.example.com/v1 failed', 'GET https://********@api.example.com/v1 failed'],
    'no credentials' => ['GET https://api.example.com/v1?to=a@b.c failed', 'GET https://api.example.com/v1?to=a@b.c failed'],
]);

it('redacts secret key value pairs', function (string $message, string $expected) {
    expect(SecretRedactor::redact($message))->toBe($expected);
})->with([
    'password' => ['auth password=hunter2 rejected', 'auth password=******** rejected'],
    'secret in json' => ['{"client_secret": "abc-def"}', '{"client_secret": "********"}'],
    'token in a query' => ['/cb?access_token=xyz&state=1', '/cb?access_token=********&state=1'],
    'signature' => ['X-Amz-Signature=deadbeef', 'X-Amz-Signature=********'],
    'plain words stay' => ['The token expired and the password is wrong', 'The token expired and the password is wrong'],
]);

it('redacts authorization and bearer values', function () {
    expect(SecretRedactor::redact('Header Bearer sk_live_123 sent'))->toBe('Header Bearer ******** sent')
        ->and(SecretRedactor::redact('Authorization: Basic dXNlcjpwYXNz'))->not->toContain('dXNlcjpwYXNz')
        ->and(SecretRedactor::redact('Authorization: Bearer sk_live_123'))->not->toContain('sk_live_123');
});

it('uses a custom mask', function () {
    expect(SecretRedactor::redact('password=abc', mask: '[redacted]'))->toBe('password=[redacted]');
});

it('shows the fallback instead of the message when a secret is short', function () {
    expect(SecretRedactor::forDisplay('Login as ab failed', ['ab'], 'Generic failure.'))->toBe('Generic failure.')
        ->and(SecretRedactor::forDisplay('Login with long-secret failed', ['long-secret'], 'Generic failure.'))
        ->toBe('Login with ******** failed')
        ->and(SecretRedactor::forDisplay('Connection refused', [''], 'Generic failure.'))->toBe('Connection refused');
});
