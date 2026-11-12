<?php

use App\Models\Setting;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

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
    ]);
});
