<?php

use App\Providers\SettingsServiceProvider;
use App\Services\Settings;
use App\Support\SettingsConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'app.name' => 'EnvName',
        'mail.default' => 'array',
        'mail.mailers.smtp.host' => 'env-host.test',
        'mail.from.address' => 'env@example.com',
        'services.paypal.client_id' => 'env-client',
        'services.paypal.secret' => 'env-secret',
        'services.paypal.mode' => 'sandbox',
        'services.sepay.api_token' => 'env-token',
        'services.sepay.base_url' => 'https://my.sepay.vn',
    ]);
});

test('without saved settings the env configuration stays in effect', function () {
    SettingsConfigurator::apply();

    expect(config('app.name'))->toBe('EnvName')
        ->and(config('mail.default'))->toBe('array')
        ->and(config('mail.mailers.smtp.host'))->toBe('env-host.test')
        ->and(config('mail.from.address'))->toBe('env@example.com')
        ->and(config('services.paypal.client_id'))->toBe('env-client')
        ->and(config('services.paypal.secret'))->toBe('env-secret')
        ->and(config('services.sepay.api_token'))->toBe('env-token');
});

test('saved mail settings override the mail configuration', function () {
    Settings::set('mail_mailer', 'smtp');
    Settings::set('mail_host', 'smtp.example.com');
    Settings::set('mail_port', 465);
    Settings::set('mail_encryption', 'ssl');
    Settings::set('mail_username', 'mailer-user');
    Settings::set('mail_password', 'mailer-pass');
    Settings::set('mail_from_address', 'noreply@example.com');
    Settings::set('mail_from_name', 'Example Site');

    SettingsConfigurator::apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.username'))->toBe('mailer-user')
        ->and(config('mail.mailers.smtp.password'))->toBe('mailer-pass')
        ->and(config('mail.from.address'))->toBe('noreply@example.com')
        ->and(config('mail.from.name'))->toBe('Example Site');
});

test('a connection without encryption turns automatic TLS off', function () {
    Settings::set('mail_mailer', 'smtp');
    Settings::set('mail_encryption', 'none');

    SettingsConfigurator::apply();

    expect(config('mail.mailers.smtp.scheme'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.auto_tls'))->toBeFalse()
        ->and(config('mail.mailers.smtp.require_tls'))->toBeFalse();
});

test('saved PayPal and SePay settings override the services configuration', function () {
    Settings::set('paypal_client_id', 'db-client');
    Settings::set('paypal_secret', 'db-secret');
    Settings::set('paypal_mode', 'live');
    Settings::set('sepay_api_token', 'db-token');
    Settings::set('sepay_base_url', 'https://sepay.example.com');

    SettingsConfigurator::apply();

    expect(config('services.paypal.client_id'))->toBe('db-client')
        ->and(config('services.paypal.secret'))->toBe('db-secret')
        ->and(config('services.paypal.mode'))->toBe('live')
        ->and(config('services.sepay.api_token'))->toBe('db-token')
        ->and(config('services.sepay.base_url'))->toBe('https://sepay.example.com');
});

test('an empty payment setting falls back to the env value', function () {
    Settings::set('paypal_client_id', '');

    SettingsConfigurator::apply();

    expect(config('services.paypal.client_id'))->toBe('env-client');
});

test('an unreachable settings store keeps the env configuration at boot', function () {
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('cache down'));

    (new SettingsServiceProvider(app()))->boot();

    expect(config('app.name'))->toBe('EnvName')
        ->and(config('services.paypal.client_id'))->toBe('env-client');
});

test('the commands that print or cache the configuration skip the overrides', function (string $command) {
    Settings::set('site_name', 'ShortPay');
    $argv = $_SERVER['argv'];
    $_SERVER['argv'] = ['artisan', $command];

    try {
        (new SettingsServiceProvider(app()))->boot();
    } finally {
        $_SERVER['argv'] = $argv;
    }

    expect(config('app.name'))->toBe('EnvName');
})->with(['config:cache', 'config:show', 'optimize', 'about']);

test('the site name setting replaces the app name', function () {
    Settings::set('site_name', 'ShortPay');

    SettingsConfigurator::apply();

    expect(config('app.name'))->toBe('ShortPay');
});
