<?php

use App\Contracts\CaptchaVerifier;
use App\Enums\CaptchaProvider;
use App\Services\Settings;
use App\Services\Views\FakeCaptchaVerifier;
use App\Services\Views\HCaptchaVerifier;
use App\Services\Views\RecaptchaVerifier;
use App\Services\Views\TurnstileVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.turnstile.secret_key' => 'turnstile-secret',
        'services.hcaptcha.secret_key' => 'hcaptcha-secret',
        'services.recaptcha.secret_key' => 'recaptcha-secret',
    ]);
});

dataset('verifiers', [
    'turnstile' => [TurnstileVerifier::class, 'https://challenges.cloudflare.com/turnstile/v0/siteverify', 'turnstile-secret'],
    'hcaptcha' => [HCaptchaVerifier::class, 'https://api.hcaptcha.com/siteverify', 'hcaptcha-secret'],
    'recaptcha' => [RecaptchaVerifier::class, 'https://www.google.com/recaptcha/api/siteverify', 'recaptcha-secret'],
]);

it('accepts a response the provider confirms', function (string $class, string $endpoint, string $secret) {
    Http::fake([$endpoint => Http::response(['success' => true])]);

    expect(app($class)->verify('widget-response', '203.0.113.5'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === $endpoint
        && $request['secret'] === $secret
        && $request['response'] === 'widget-response'
        && $request['remoteip'] === '203.0.113.5');
})->with('verifiers');

it('rejects a response the provider refuses', function (string $class, string $endpoint) {
    Http::fake([$endpoint => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    expect(app($class)->verify('widget-response', '203.0.113.5'))->toBeFalse();
})->with('verifiers');

it('rejects when the provider answers with a server error', function (string $class, string $endpoint) {
    Http::fake([$endpoint => Http::response('error', 500)]);

    expect(app($class)->verify('widget-response', '203.0.113.5'))->toBeFalse();
})->with('verifiers');

it('rejects an empty response without calling the provider', function (string $class) {
    Http::fake();

    expect(app($class)->verify('', '203.0.113.5'))->toBeFalse();

    Http::assertNothingSent();
})->with('verifiers');

it('rejects every response when the secret key is missing', function (string $class) {
    config([
        'services.turnstile.secret_key' => null,
        'services.hcaptcha.secret_key' => null,
        'services.recaptcha.secret_key' => null,
    ]);
    Http::fake();

    expect(app($class)->verify('widget-response', '203.0.113.5'))->toBeFalse();

    Http::assertNothingSent();
})->with('verifiers');

it('selects the verifier from the captcha_provider setting', function (string $provider, string $class) {
    Settings::set('captcha_provider', $provider);

    expect(CaptchaProvider::current()->verifier())->toBeInstanceOf($class);
})->with([
    'turnstile' => ['turnstile', TurnstileVerifier::class],
    'hcaptcha' => ['hcaptcha', HCaptchaVerifier::class],
    'recaptcha' => ['recaptcha', RecaptchaVerifier::class],
]);

it('falls back to turnstile when the setting is unknown', function () {
    Settings::set('captcha_provider', 'something-else');

    expect(CaptchaProvider::current())->toBe(CaptchaProvider::Turnstile);
});

it('uses turnstile by default', function () {
    expect(CaptchaProvider::current())->toBe(CaptchaProvider::Turnstile);
});

it('lets the admin setting override the configured secret key', function () {
    Settings::set('turnstile_secret_key', 'admin-secret');
    Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    app(TurnstileVerifier::class)->verify('widget-response', '203.0.113.5');

    Http::assertSent(fn (Request $request): bool => $request['secret'] === 'admin-secret');
});

it('binds the fake verifier in the testing environment', function () {
    expect(app(CaptchaVerifier::class))->toBeInstanceOf(FakeCaptchaVerifier::class);
});
