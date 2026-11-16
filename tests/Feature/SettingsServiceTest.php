<?php

use App\Models\Setting;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

it('returns the config default when no row exists', function (): void {
    expect(Settings::get('dwell_seconds'))->toBe(10)
        ->and(Settings::int('min_withdraw_micro'))->toBe(5000000)
        ->and(Settings::get('unknown_key', 'fallback'))->toBe('fallback')
        ->and(Settings::get('unknown_key'))->toBeNull();
});

it('returns the stored value after set', function (): void {
    Settings::set('dwell_seconds', 15);

    expect(Settings::get('dwell_seconds'))->toBe('15')
        ->and(Settings::int('dwell_seconds'))->toBe(15);
    $this->assertDatabaseHas('settings', ['key' => 'dwell_seconds', 'value' => '15']);
});

it('overwrites an existing value', function (): void {
    Settings::set('review_days', 3);
    Settings::set('review_days', 7);

    expect(Settings::int('review_days'))->toBe(7)
        ->and(Setting::query()->where('key', 'review_days')->count())->toBe(1);
});

it('clears the cache when a value is set', function (): void {
    Settings::set('alias_length', 6);
    expect(Settings::int('alias_length'))->toBe(6);
    expect(Cache::has('settings.all'))->toBeTrue();

    Settings::set('alias_length', 8);

    expect(Cache::has('settings.all'))->toBeFalse()
        ->and(Settings::int('alias_length'))->toBe(8);
});

it('stores secret settings encrypted and reads them back in plain text', function (): void {
    Settings::set('paypal_secret', 'paypal-secret-value');

    $stored = Setting::query()->where('key', 'paypal_secret')->value('value');

    expect($stored)->not->toBe('paypal-secret-value')
        ->and(Crypt::decryptString($stored))->toBe('paypal-secret-value')
        ->and(Settings::get('paypal_secret'))->toBe('paypal-secret-value');
});

it('stores non-secret settings in plain text', function (): void {
    Settings::set('paypal_client_id', 'client-id');

    $this->assertDatabaseHas('settings', ['key' => 'paypal_client_id', 'value' => 'client-id']);
});

it('still reads a legacy plain-text secret', function (): void {
    Setting::factory()->create(['key' => 'turnstile_secret_key', 'value' => 'legacy-plain-secret']);

    expect(Settings::get('turnstile_secret_key'))->toBe('legacy-plain-secret');
});

it('forgets a stored setting so the default applies again', function (): void {
    Settings::set('dwell_seconds', 20);
    Settings::forget('dwell_seconds');

    expect(Settings::int('dwell_seconds'))->toBe(10)
        ->and(Settings::has('dwell_seconds'))->toBeFalse();
});

it('encrypts legacy plain-text secrets in the data migration and leaves encrypted ones alone', function (): void {
    Setting::factory()->create(['key' => 'hcaptcha_secret_key', 'value' => 'legacy-plain-secret']);
    Settings::set('recaptcha_secret_key', 'already-encrypted');
    $encrypted = Setting::query()->where('key', 'recaptcha_secret_key')->value('value');
    Setting::factory()->create(['key' => 'hcaptcha_site_key', 'value' => 'public-site-key']);

    $migration = require database_path('migrations/2026_11_16_004016_encrypt_secret_settings.php');
    $migration->up();

    $hcaptcha = Setting::query()->where('key', 'hcaptcha_secret_key')->value('value');

    expect($hcaptcha)->not->toBe('legacy-plain-secret')
        ->and(Crypt::decryptString($hcaptcha))->toBe('legacy-plain-secret')
        ->and(Setting::query()->where('key', 'recaptcha_secret_key')->value('value'))->toBe($encrypted)
        ->and(Setting::query()->where('key', 'hcaptcha_site_key')->value('value'))->toBe('public-site-key')
        ->and(Settings::get('hcaptcha_secret_key'))->toBe('legacy-plain-secret');
});

it('exposes the project defaults in config', function (): void {
    expect(config('linkcash.defaults'))->toBe([
        'dwell_seconds' => 10,
        'unique_window_hours' => 24,
        'review_days' => 3,
        'min_withdraw_micro' => 5000000,
        'referral_percent' => 12,
        'session_minutes' => 30,
        'view_purge_days' => 60,
        'alias_length' => 6,
        'captcha_provider' => 'turnstile',
        'visit_mode' => 'auto',
        'code_session_seconds' => 600,
        'widget_attempts_per_ip_per_day' => 5,
        'tutorial_video_url' => '',
        'advertiser_default_price_micro' => 10_000,
        'advertiser_min_topup_micro' => 5_000_000,
        'vnd_per_usd' => 25_000,
        'sepay_bank_name' => '',
        'sepay_account_number' => '',
        'sepay_account_name' => '',
        'payout_methods' => 'paypal,bank_transfer,momo,usdt',
    ]);
});
