<?php

use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A valid payload for the general settings form, with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function generalSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'site_name' => 'ShortPay',
        'support_email' => 'help@shortpay.test',
        'social_facebook' => 'https://www.facebook.com/shortpay',
        'social_telegram' => '',
        'registration_open' => '1',
        'maintenance_mode' => '0',
        'maintenance_message' => 'Payouts are delayed until Monday.',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins save the general settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.general.update'), generalSettingsPayload(['registration_open' => '0']))
        ->assertRedirect(route('admin.settings.general.edit'))
        ->assertSessionHas('success');

    expect(Settings::get('site_name'))->toBe('ShortPay')
        ->and(Settings::get('support_email'))->toBe('help@shortpay.test')
        ->and(Settings::get('social_facebook'))->toBe('https://www.facebook.com/shortpay')
        ->and(Settings::get('social_telegram'))->toBe('')
        ->and(Settings::bool('registration_open'))->toBeFalse()
        ->and(Settings::bool('maintenance_mode'))->toBeFalse()
        ->and(Settings::get('maintenance_message'))->toBe('Payouts are delayed until Monday.');
});

test('the general page shows the saved values', function () {
    Settings::set('support_email', 'help@shortpay.test');
    Settings::set('social_telegram', 'https://t.me/shortpay');

    $this->actingAs($this->admin)
        ->get(route('admin.settings.general.edit'))
        ->assertOk()
        ->assertSee('help@shortpay.test')
        ->assertSee('https://t.me/shortpay');
});

test('invalid general settings are rejected', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.general.update'), generalSettingsPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Settings::has('site_name'))->toBeFalse();
})->with([
    'missing site name' => [['site_name' => ''], 'site_name'],
    'site name with a line break' => [['site_name' => "Short\nPay"], 'site_name'],
    'site name with a tab' => [['site_name' => "Short\tPay"], 'site_name'],
    'invalid support email' => [['support_email' => 'not-an-email'], 'support_email'],
    'social link that is not a URL' => [['social_facebook' => 'facebook.com/shortpay'], 'social_facebook'],
    'social link with another scheme' => [['social_facebook' => 'javascript:alert(1)'], 'social_facebook'],
    'notice that is too long' => [['maintenance_message' => str_repeat('a', 501)], 'maintenance_message'],
]);
