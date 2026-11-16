<?php

use App\Enums\CaptchaProvider;
use App\Enums\SettingsPage;
use App\Models\CpmRate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A valid payload for the traffic and earnings settings form, with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    return array_merge([
        'dwell_seconds' => 12,
        'unique_window_hours' => 24,
        'review_days' => 3,
        'referral_percent' => 12,
        'session_minutes' => 30,
        'view_purge_days' => 60,
        'alias_length' => 6,
        'visit_mode' => 'auto',
        'code_session_seconds' => 600,
        'tutorial_video_url' => '',
        'advertiser_default_price_usd' => '0.01',
        'login_purge_days' => 90,
        'multi_account_shared_ip_limit' => 10,
        'signups_per_ip_per_day' => 5,
        'referral_hold_on_suspicion' => '0',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('members cannot open the settings and rates pages', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs($member)->put(route('admin.settings.traffic.update'), settingsPayload())->assertForbidden();
    $this->actingAs($member)->get(route('admin.rates.index'))->assertForbidden();
    $this->actingAs($member)->post(route('admin.rates.store'), [])->assertForbidden();
});

test('the old settings address opens the first settings page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.settings.edit'))
        ->assertRedirect(route(SettingsPage::cases()[0]->routeName()));
});

test('admins can change the dwell time', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.traffic.update'), settingsPayload(['dwell_seconds' => 15]))
        ->assertRedirect(route('admin.settings.traffic.edit'));

    expect(Settings::int('dwell_seconds'))->toBe(15);
});

test('dwell seconds outside 5 to 60 are rejected', function (int $value) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.traffic.update'), settingsPayload(['dwell_seconds' => $value]))
        ->assertSessionHasErrors('dwell_seconds');

    expect(Settings::int('dwell_seconds'))->toBe(10);
})->with([4, 61]);

test('admins can change the multi-account and sign-up settings', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.traffic.edit'))
        ->assertOk()
        ->assertSee('Sign-ups per IP address per day')
        ->assertSee('Hold suspicious referral commission');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.traffic.update'), settingsPayload([
            'login_purge_days' => 30,
            'multi_account_shared_ip_limit' => 25,
            'signups_per_ip_per_day' => 0,
            'referral_hold_on_suspicion' => '1',
        ]))
        ->assertRedirect(route('admin.settings.traffic.edit'));

    expect(Settings::int('login_purge_days'))->toBe(30)
        ->and(Settings::int('multi_account_shared_ip_limit'))->toBe(25)
        ->and(Settings::int('signups_per_ip_per_day'))->toBe(0)
        ->and(Settings::bool('referral_hold_on_suspicion'))->toBeTrue();
});

test('multi-account and sign-up settings outside their ranges are rejected', function (string $field, mixed $value) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.traffic.update'), settingsPayload([$field => $value]))
        ->assertSessionHasErrors($field);
})->with([
    ['login_purge_days', 6],
    ['login_purge_days', 366],
    ['multi_account_shared_ip_limit', 1],
    ['multi_account_shared_ip_limit', 1001],
    ['signups_per_ip_per_day', -1],
    ['signups_per_ip_per_day', 1001],
    ['referral_hold_on_suspicion', 'maybe'],
]);

test('an unknown captcha provider is rejected', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.captcha.update'), ['captcha_provider' => 'nope'])
        ->assertSessionHasErrors('captcha_provider');
});

test('captcha secrets are stored encrypted but never shown again, and a blank secret keeps the old one', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.captcha.update'), [
        'captcha_provider' => 'hcaptcha',
        'hcaptcha_site_key' => 'site-key-1',
        'hcaptcha_secret_key' => 'super-secret-value',
    ])->assertRedirect(route('admin.settings.captcha.edit'));

    expect(Settings::get('hcaptcha_secret_key'))->toBe('super-secret-value')
        ->and(Setting::query()->where('key', 'hcaptcha_secret_key')->value('value'))->not->toContain('super-secret-value')
        ->and(Settings::get('captcha_provider'))->toBe('hcaptcha');

    $this->actingAs($this->admin)->get(route('admin.settings.captcha.edit'))
        ->assertOk()
        ->assertSee('site-key-1')
        ->assertSee('Saved')
        ->assertDontSee('super-secret-value');

    $this->actingAs($this->admin)->put(route('admin.settings.captcha.update'), [
        'captcha_provider' => 'hcaptcha',
        'hcaptcha_site_key' => 'site-key-2',
        'hcaptcha_secret_key' => '',
    ]);

    expect(Settings::get('hcaptcha_secret_key'))->toBe('super-secret-value')
        ->and(Settings::get('hcaptcha_site_key'))->toBe('site-key-2');
});

test('a saved captcha secret can be removed so the server default applies again', function () {
    config(['services.hcaptcha.secret_key' => 'env-secret']);
    Settings::set('hcaptcha_secret_key', 'old-secret');

    $this->actingAs($this->admin)->put(route('admin.settings.captcha.update'), [
        'captcha_provider' => 'hcaptcha',
        'clear_hcaptcha_secret_key' => '1',
    ]);

    expect(Settings::has('hcaptcha_secret_key'))->toBeFalse()
        ->and(CaptchaProvider::HCaptcha->secretKey())->toBe('env-secret');
});

test('a blank captcha site key falls back to the server default', function () {
    config(['services.turnstile.site_key' => 'env-site-key']);
    Settings::set('turnstile_site_key', 'old-site-key');

    $this->actingAs($this->admin)->put(route('admin.settings.captcha.update'), [
        'captcha_provider' => 'turnstile',
        'turnstile_site_key' => '',
    ]);

    expect(CaptchaProvider::Turnstile->siteKey())->toBe('env-site-key');
});

test('admins can add, update and delete a country rate', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.rates.store'), ['country' => 'br', 'level_1' => 1.25, 'level_2' => 2, 'level_3' => 3.5])
        ->assertRedirect(route('admin.rates.index'));

    expect(CpmRate::query()->where('country', 'BR')->where('level', 1)->value('rate_per_1000_micro'))->toBe(1_250_000)
        ->and(CpmRate::query()->where('country', 'BR')->count())->toBe(3);

    $this->actingAs($this->admin)
        ->put(route('admin.rates.update', 'BR'), ['level_1' => 9, 'level_2' => 9, 'level_3' => 9]);

    expect(CpmRate::query()->where('country', 'BR')->where('level', 3)->value('rate_per_1000_micro'))->toBe(9_000_000);

    $this->actingAs($this->admin)->delete(route('admin.rates.destroy', 'BR'));

    expect(CpmRate::query()->where('country', 'BR')->count())->toBe(0);
});

test('an invalid country code is rejected', function (string $code) {
    $this->actingAs($this->admin)
        ->post(route('admin.rates.store'), ['country' => $code, 'level_1' => 1, 'level_2' => 1, 'level_3' => 1])
        ->assertSessionHasErrors('country');

    expect(CpmRate::query()->count())->toBe(0);
})->with(['USA', 'U1', '1', '']);

test('the public rates page shows the rates from the database', function () {
    CpmRate::factory()->create(['country' => 'BR', 'level' => 1, 'rate_per_1000_micro' => 1_230_000]);
    CpmRate::factory()->create(['country' => 'BR', 'level' => 2, 'rate_per_1000_micro' => 2_340_000]);
    CpmRate::factory()->create(['country' => 'BR', 'level' => 3, 'rate_per_1000_micro' => 3_450_000]);

    $this->get(route('payout-rates'))
        ->assertOk()
        ->assertSee('$1.23')
        ->assertSee('$2.34')
        ->assertSee('$3.45');
});
