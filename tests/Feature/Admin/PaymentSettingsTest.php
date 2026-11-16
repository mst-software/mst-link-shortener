<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A valid payload for the payments settings form, with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'min_withdraw_usd' => '7.5',
        'payout_methods' => ['paypal', 'usdt'],
        'advertiser_min_topup_usd' => '10',
        'vnd_per_usd' => 26_000,
        'paypal_client_id' => 'paypal-client',
        'paypal_secret' => '',
        'paypal_mode' => 'live',
        'sepay_api_token' => '',
        'sepay_base_url' => 'https://my.sepay.vn',
        'sepay_bank_name' => 'MBBank',
        'sepay_account_number' => '0123456789',
        'sepay_account_name' => 'LINKCASH CO',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins save the payment settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.payments.update'), paymentSettingsPayload())
        ->assertRedirect(route('admin.settings.payments.edit'))
        ->assertSessionHas('success');

    expect(Settings::int('min_withdraw_micro'))->toBe(7_500_000)
        ->and(Settings::get('payout_methods'))->toBe('paypal,usdt')
        ->and(Settings::int('advertiser_min_topup_micro'))->toBe(10_000_000)
        ->and(Settings::int('vnd_per_usd'))->toBe(26_000)
        ->and(Settings::get('paypal_client_id'))->toBe('paypal-client')
        ->and(Settings::get('paypal_mode'))->toBe('live')
        ->and(Settings::get('sepay_base_url'))->toBe('https://my.sepay.vn');
});

test('payment secrets are stored encrypted and never rendered back', function () {
    $this->actingAs($this->admin)->put(route('admin.settings.payments.update'), paymentSettingsPayload([
        'paypal_secret' => 'paypal-secret-value',
        'sepay_api_token' => 'sepay-token-value',
    ]));

    expect(Settings::get('paypal_secret'))->toBe('paypal-secret-value')
        ->and(Settings::get('sepay_api_token'))->toBe('sepay-token-value')
        ->and(Setting::query()->where('key', 'paypal_secret')->value('value'))->not->toContain('paypal-secret-value')
        ->and(Setting::query()->where('key', 'sepay_api_token')->value('value'))->not->toContain('sepay-token-value');

    $this->actingAs($this->admin)->get(route('admin.settings.payments.edit'))
        ->assertOk()
        ->assertSee('Saved')
        ->assertDontSee('paypal-secret-value')
        ->assertDontSee('sepay-token-value');
});

test('a blank secret keeps the saved one and the clear box removes it', function () {
    Settings::set('paypal_secret', 'paypal-secret-value');
    Settings::set('sepay_api_token', 'sepay-token-value');

    $this->actingAs($this->admin)->put(route('admin.settings.payments.update'), paymentSettingsPayload([
        'clear_sepay_api_token' => '1',
    ]));

    expect(Settings::get('paypal_secret'))->toBe('paypal-secret-value')
        ->and(Settings::has('sepay_api_token'))->toBeFalse();
});

test('at least one payout method must stay switched on', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.payments.update'), paymentSettingsPayload(['payout_methods' => []]))
        ->assertSessionHasErrors(['payout_methods' => 'Keep at least one payout method switched on.']);

    expect(Settings::get('payout_methods'))->toBe('paypal,bank_transfer,momo,usdt');
});

test('invalid payment settings are rejected', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.payments.update'), paymentSettingsPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'unknown payout method' => [['payout_methods' => ['cheque']], 'payout_methods.0'],
    'unknown PayPal mode' => [['paypal_mode' => 'production'], 'paypal_mode'],
    'SePay address that is not a URL' => [['sepay_base_url' => 'not a url'], 'sepay_base_url'],
    'minimum withdrawal below 1' => [['min_withdraw_usd' => '0.5'], 'min_withdraw_usd'],
]);

test('members cannot save the payment settings', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('admin.settings.payments.update'), paymentSettingsPayload())
        ->assertForbidden();

    expect(Settings::has('payout_methods'))->toBeFalse();
});

test('long API tokens up to 1000 characters are accepted', function () {
    $token = str_repeat('t', 1000);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.payments.update'), paymentSettingsPayload(['sepay_api_token' => $token]))
        ->assertSessionHasNoErrors();

    expect(Settings::get('sepay_api_token'))->toBe($token);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.payments.update'), paymentSettingsPayload(['sepay_api_token' => $token.'t']))
        ->assertSessionHasErrors('sepay_api_token');
});
