<?php

use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Enums\TransactionAction;
use App\Models\TopUp;
use App\Models\User;
use App\Services\Settings;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::set('sepay_bank_name', 'MBBank');
    Settings::set('sepay_account_number', '0123456789');
    Settings::set('sepay_account_name', 'LINKCASH CO');
    $this->advertiser = User::factory()->advertiser()->create(['balance_micro' => 12_340_000]);
});

it('shows balance, pending sepay qr details and history', function () {
    $pending = TopUp::factory()->create([
        'user_id' => $this->advertiser->id,
        'reference' => 'LC1PENDING',
        'original_amount' => 250_000,
        'amount_micro' => 10_000_000,
    ]);
    TopUp::factory()->create([
        'user_id' => $this->advertiser->id,
        'provider' => TopUpProvider::Paypal,
        'reference' => 'PAYPAL-ORDER-9',
        'status' => TopUpStatus::Completed,
        'amount_micro' => 20_000_000,
    ]);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.wallet'))
        ->assertOk()
        ->assertSee('$12.34')
        ->assertSee('0123456789')
        ->assertSee('LINKCASH CO')
        ->assertSee('LC1PENDING')
        ->assertSee('250,000')
        ->assertSee(e($pending->sepayQrUrl()), false)
        ->assertSee('Bank transfer (SePay)')
        ->assertSee('PayPal')
        ->assertSee('Completed');
});

it('shows my transactions', function () {
    app(WalletService::class)->credit($this->advertiser, 5_000_000, TransactionAction::Topup, 'topup:1', 'Bank transfer (SePay) top up');

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.wallet'))
        ->assertSee('Top up')
        ->assertSee('$17.34');
});

it('shows only my top ups and transactions', function () {
    $other = User::factory()->advertiser()->create();
    TopUp::factory()->create(['user_id' => $other->id, 'reference' => 'LC999OTHERS']);
    app(WalletService::class)->credit($other, 7_770_000, TransactionAction::Topup, 'topup:2', 'Other top up');

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.wallet'))
        ->assertDontSee('LC999OTHERS')
        ->assertDontSee('Other top up');
});

it('does not offer an expired transfer as pending', function () {
    TopUp::factory()->create([
        'user_id' => $this->advertiser->id,
        'reference' => 'LC1EXPIRED',
        'expires_at' => now()->subHour(),
    ]);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.wallet'))
        ->assertDontSee('Waiting for your transfer');
});

it('shows the minimum top up and the current rate', function () {
    Settings::set('vnd_per_usd', 26_000);
    Settings::set('advertiser_min_topup_micro', 10_000_000);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.wallet'))
        ->assertSee('$10.00')
        ->assertSee('26,000')
        ->assertSee('260,000');
});

it('is closed to members', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('advertiser.wallet'))
        ->assertForbidden();
});
