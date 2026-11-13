<?php

use App\Enums\InvalidReason;
use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Enums\TransactionAction;
use App\Enums\UserStatus;
use App\Enums\ViewStatus;
use App\Models\Campaign;
use App\Models\CpmRate;
use App\Models\TopUp;
use App\Models\Transaction;
use App\Models\User;
use App\Models\View;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * A settings form payload with the advertiser fields, plus optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function advertiserSettingsPayload(array $overrides = []): array
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
        'advertiser_default_price_usd' => '0.012',
        'advertiser_min_topup_usd' => '10',
        'vnd_per_usd' => 26_000,
        'sepay_bank_name' => 'MBBank',
        'sepay_account_number' => '0123456789',
        'sepay_account_name' => 'LINKCASH CO',
    ], $overrides);
}

it('lists advertisers with balance, total topped up and total spent', function () {
    $advertiser = User::factory()->advertiser()->create(['username' => 'big_spender', 'balance_micro' => 7_000_000]);
    $campaign = Campaign::factory()->forAdvertiser($advertiser)->create();
    TopUp::factory()->create(['user_id' => $advertiser->id, 'status' => TopUpStatus::Completed, 'amount_micro' => 20_000_000]);
    TopUp::factory()->create(['user_id' => $advertiser->id, 'status' => TopUpStatus::Pending, 'amount_micro' => 99_000_000]);
    View::factory()->create(['campaign_id' => $campaign->id, 'status' => ViewStatus::Pending, 'advertiser_cost_micro' => 30_000]);
    View::factory()->create(['campaign_id' => $campaign->id, 'status' => ViewStatus::Cancelled, 'advertiser_cost_micro' => 50_000]);
    User::factory()->create(['username' => 'plain_publisher']);

    $this->actingAs($this->admin)
        ->get(route('admin.advertisers.index'))
        ->assertOk()
        ->assertSee('big_spender')
        ->assertSee('$7.00')
        ->assertSee('$20.00')
        ->assertSee('$0.03')
        ->assertDontSee('$99.00')
        ->assertDontSee('plain_publisher');
});

it('locks and unlocks an advertiser', function () {
    $advertiser = User::factory()->advertiser()->create(['balance_micro' => 1_000_000]);
    $campaign = Campaign::factory()->forAdvertiser($advertiser)->create();

    $this->actingAs($this->admin)->patch(route('admin.advertisers.toggle', $advertiser))->assertRedirect();

    expect($advertiser->refresh()->status)->toBe(UserStatus::Banned)
        ->and(Campaign::query()->available()->whereKey($campaign->id)->exists())->toBeFalse();

    $this->actingAs($this->admin)->patch(route('admin.advertisers.toggle', $advertiser))->assertRedirect();

    expect($advertiser->refresh()->status)->toBe(UserStatus::Active)
        ->and(Campaign::query()->available()->whereKey($campaign->id)->exists())->toBeTrue();
});

it('shows an advertiser with campaigns, top ups, transactions and the reason of invalid visits', function () {
    $advertiser = User::factory()->advertiser()->create(['username' => 'detail_owner']);
    $campaign = Campaign::factory()->forAdvertiser($advertiser)->create(['name' => 'Detail campaign']);
    TopUp::factory()->create(['user_id' => $advertiser->id, 'reference' => 'LC77DETAIL', 'status' => TopUpStatus::Mismatch]);
    Transaction::factory()->create(['user_id' => $advertiser->id, 'action' => TransactionAction::Topup]);
    View::factory()->create(['campaign_id' => $campaign->id, 'status' => ViewStatus::Invalid, 'invalid_reason' => InvalidReason::BlockedNetwork]);

    $this->actingAs($this->admin)
        ->get(route('admin.advertisers.show', $advertiser))
        ->assertOk()
        ->assertSee('detail_owner')
        ->assertSee('Detail campaign')
        ->assertSee('LC77DETAIL')
        ->assertSee('Needs review')
        ->assertSee('Top up')
        ->assertSee('Blocked network');
});

it('credits a mismatched top up once from the admin screen', function () {
    $advertiser = User::factory()->advertiser()->create();
    $topUp = TopUp::factory()->create(['user_id' => $advertiser->id, 'status' => TopUpStatus::Mismatch, 'amount_micro' => 4_000_000]);

    $this->actingAs($this->admin)->post(route('admin.top-ups.credit', $topUp))->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.top-ups.credit', $topUp))->assertRedirect();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Completed)
        ->and($advertiser->refresh()->balance_micro)->toBe(4_000_000)
        ->and(Transaction::query()->where('action', TransactionAction::Topup)->count())->toBe(1);
});

it('refuses to credit a top up that is not a mismatch', function () {
    $advertiser = User::factory()->advertiser()->create();
    $topUp = TopUp::factory()->create(['user_id' => $advertiser->id, 'status' => TopUpStatus::Pending, 'provider' => TopUpProvider::Sepay]);

    $this->actingAs($this->admin)
        ->post(route('admin.top-ups.credit', $topUp))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($advertiser->refresh()->balance_micro)->toBe(0)
        ->and($topUp->refresh()->status)->toBe(TopUpStatus::Pending);
});

it('forbids non-admins', function () {
    $advertiser = User::factory()->advertiser()->create();
    $topUp = TopUp::factory()->create(['user_id' => $advertiser->id, 'status' => TopUpStatus::Mismatch]);

    foreach ([User::factory()->create(), $advertiser] as $user) {
        $this->actingAs($user)->get(route('admin.advertisers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.advertisers.show', $advertiser))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.advertisers.toggle', $advertiser))->assertForbidden();
        $this->actingAs($user)->post(route('admin.top-ups.credit', $topUp))->assertForbidden();
    }

    expect($advertiser->refresh()->balance_micro)->toBe(0);
});

it('saves the advertiser settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), advertiserSettingsPayload())
        ->assertRedirect(route('admin.settings.edit'));

    expect(Settings::int('advertiser_default_price_micro'))->toBe(12_000)
        ->and(Settings::int('advertiser_min_topup_micro'))->toBe(10_000_000)
        ->and(Settings::int('vnd_per_usd'))->toBe(26_000)
        ->and(Settings::get('sepay_bank_name'))->toBe('MBBank')
        ->and(Settings::get('sepay_account_number'))->toBe('0123456789')
        ->and(Settings::get('sepay_account_name'))->toBe('LINKCASH CO');
});

it('rejects invalid advertiser settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), advertiserSettingsPayload(['advertiser_default_price_usd' => '0', 'vnd_per_usd' => 0]))
        ->assertSessionHasErrors(['advertiser_default_price_usd', 'vnd_per_usd']);
});

it('shows the advertiser settings on the settings page', function () {
    Settings::set('sepay_account_number', '555666777');

    $this->actingAs($this->admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('555666777')
        ->assertSee('advertiser_default_price_usd');
});

it('warns when the price is below the highest publisher rate', function () {
    CpmRate::factory()->create(['country' => 'US', 'level' => 3, 'rate_per_1000_micro' => 20_000_000]);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), advertiserSettingsPayload(['advertiser_default_price_usd' => '0.01']))
        ->assertSessionHas('warning');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), advertiserSettingsPayload(['advertiser_default_price_usd' => '0.05']))
        ->assertSessionMissing('warning');
});

it('stores a per campaign price and warns when it is too low', function () {
    CpmRate::factory()->create(['country' => 'US', 'level' => 3, 'rate_per_1000_micro' => 20_000_000]);
    $campaign = Campaign::factory()->forAdvertiser()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.campaigns.update', $campaign), [
            'name' => $campaign->name,
            'keyword' => $campaign->keyword,
            'domain' => $campaign->domain,
            'weight' => 1,
            'daily_limit' => 0,
            'stage1_seconds' => 60,
            'stage2_seconds' => 15,
            'price_usd' => '0.015',
        ])
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect($campaign->refresh()->price_micro)->toBe(15_000);

    $this->actingAs($this->admin)
        ->put(route('admin.campaigns.update', $campaign), [
            'name' => $campaign->name,
            'keyword' => $campaign->keyword,
            'domain' => $campaign->domain,
            'weight' => 1,
            'daily_limit' => 0,
            'stage1_seconds' => 60,
            'stage2_seconds' => 15,
            'price_usd' => '',
        ])
        ->assertRedirect();

    expect($campaign->refresh()->price_micro)->toBeNull();
});
