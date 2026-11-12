<?php

use App\Models\CpmRate;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A valid payload for the settings form, with optional overrides.
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
        'min_withdraw_usd' => 5,
        'referral_percent' => 12,
        'session_minutes' => 30,
        'view_purge_days' => 60,
        'alias_length' => 6,
        'captcha_provider' => 'turnstile',
        'visit_mode' => 'auto',
        'code_session_seconds' => 600,
        'tutorial_video_url' => '',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('members cannot open the settings and rates pages', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('admin.settings.edit'))->assertForbidden();
    $this->actingAs($member)->put(route('admin.settings.update'), settingsPayload())->assertForbidden();
    $this->actingAs($member)->get(route('admin.rates.index'))->assertForbidden();
    $this->actingAs($member)->post(route('admin.rates.store'), [])->assertForbidden();
});

test('admins can change the dwell time', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), settingsPayload(['dwell_seconds' => 15, 'min_withdraw_usd' => 7.5]))
        ->assertRedirect(route('admin.settings.edit'));

    expect(Settings::int('dwell_seconds'))->toBe(15)
        ->and(Settings::int('min_withdraw_micro'))->toBe(7_500_000);
});

test('dwell seconds outside 5 to 60 are rejected', function (int $value) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), settingsPayload(['dwell_seconds' => $value]))
        ->assertSessionHasErrors('dwell_seconds');

    expect(Settings::int('dwell_seconds'))->toBe(10);
})->with([4, 61]);

test('an unknown captcha provider is rejected', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), settingsPayload(['captcha_provider' => 'nope']))
        ->assertSessionHasErrors('captcha_provider');
});

test('captcha secrets are stored but never shown again, and a blank secret keeps the old one', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsPayload([
        'captcha_provider' => 'hcaptcha',
        'hcaptcha_site_key' => 'site-key-1',
        'hcaptcha_secret_key' => 'super-secret-value',
    ]));

    expect(Settings::get('hcaptcha_secret_key'))->toBe('super-secret-value')
        ->and(Settings::get('captcha_provider'))->toBe('hcaptcha');

    $this->actingAs($this->admin)->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('site-key-1')
        ->assertDontSee('super-secret-value');

    $this->actingAs($this->admin)->put(route('admin.settings.update'), settingsPayload([
        'captcha_provider' => 'hcaptcha',
        'hcaptcha_site_key' => 'site-key-2',
        'hcaptcha_secret_key' => '',
    ]));

    expect(Settings::get('hcaptcha_secret_key'))->toBe('super-secret-value')
        ->and(Settings::get('hcaptcha_site_key'))->toBe('site-key-2');
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
