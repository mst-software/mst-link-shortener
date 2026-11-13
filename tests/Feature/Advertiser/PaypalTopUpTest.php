<?php

use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Enums\TransactionAction;
use App\Models\TopUp;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.paypal.client_id' => 'client-id',
        'services.paypal.secret' => 'client-secret',
        'services.paypal.mode' => 'sandbox',
    ]);
    $this->advertiser = User::factory()->advertiser()->create();
});

/**
 * Fake the PayPal token, create order and capture endpoints.
 *
 * @param  array<string, mixed>  $capture
 */
function fakePaypal(array $capture = []): void
{
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token', 'expires_in' => 3200]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/*/capture' => Http::response($capture ?: paypalCapture('20.00')),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'ORDER-123',
            'status' => 'CREATED',
            'links' => [
                ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-123'],
                ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123'],
            ],
        ], 201),
    ]);
}

/**
 * @return array<string, mixed>
 */
function paypalCapture(string $value, string $status = 'COMPLETED', string $currency = 'USD'): array
{
    return [
        'id' => 'ORDER-123',
        'status' => $status,
        'purchase_units' => [[
            'payments' => ['captures' => [[
                'id' => 'CAPTURE-1',
                'status' => $status,
                'amount' => ['currency_code' => $currency, 'value' => $value],
            ]]],
        ]],
    ];
}

function pendingPaypalTopUp(User $user, int $micro = 20_000_000): TopUp
{
    return TopUp::factory()->create([
        'user_id' => $user->id,
        'provider' => TopUpProvider::Paypal,
        'reference' => 'ORDER-123',
        'amount_micro' => $micro,
        'original_amount' => $micro,
        'original_currency' => 'USD',
        'exchange_rate' => null,
    ]);
}

it('creates a PayPal order and redirects to the approval url', function () {
    fakePaypal();

    $this->actingAs($this->advertiser)
        ->post(route('advertiser.topups.paypal'), ['usd' => '20'])
        ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123');

    $topUp = TopUp::query()->sole();

    expect($topUp->provider)->toBe(TopUpProvider::Paypal)
        ->and($topUp->reference)->toBe('ORDER-123')
        ->and($topUp->status)->toBe(TopUpStatus::Pending)
        ->and($topUp->amount_micro)->toBe(20_000_000)
        ->and($topUp->original_currency)->toBe('USD');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
        && $request->hasHeader('Authorization', 'Bearer paypal-token')
        && $request['intent'] === 'CAPTURE'
        && $request['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '20.00']);
});

it('uses the live api in live mode', function () {
    config(['services.paypal.mode' => 'live']);
    Http::fake([
        'api-m.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 't']),
        'api-m.paypal.com/v2/checkout/orders' => Http::response(['id' => 'LIVE-1', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.paypal.com/checkoutnow?token=LIVE-1']]], 201),
    ]);

    $this->actingAs($this->advertiser)
        ->post(route('advertiser.topups.paypal'), ['usd' => '20'])
        ->assertRedirect('https://www.paypal.com/checkoutnow?token=LIVE-1');
});

it('refuses amounts below the minimum', function () {
    fakePaypal();

    $this->actingAs($this->advertiser)
        ->post(route('advertiser.topups.paypal'), ['usd' => '4.99'])
        ->assertSessionHasErrors('usd');

    expect(TopUp::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('shows an error and creates nothing when PayPal is unreachable', function () {
    Http::fake(['api-m.sandbox.paypal.com/*' => Http::response('boom', 500)]);

    $this->actingAs($this->advertiser)
        ->post(route('advertiser.topups.paypal'), ['usd' => '20'])
        ->assertRedirect(route('advertiser.wallet'))
        ->assertSessionHas('error');

    expect(TopUp::query()->count())->toBe(0);
});

it('credits the wallet when the capture is completed', function () {
    fakePaypal();
    $topUp = pendingPaypalTopUp($this->advertiser);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']))
        ->assertRedirect(route('advertiser.wallet'))
        ->assertSessionHas('success');

    $transaction = Transaction::query()->where('user_id', $this->advertiser->id)->sole();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Completed)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(20_000_000)
        ->and($transaction->action)->toBe(TransactionAction::Topup);
});

it('does not credit when the capture is declined', function () {
    fakePaypal(paypalCapture('20.00', 'DECLINED'));
    $topUp = pendingPaypalTopUp($this->advertiser);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']))
        ->assertRedirect(route('advertiser.wallet'))
        ->assertSessionHas('error');

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Failed)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(0);
});

it('does not credit when the captured amount differs from the order', function () {
    fakePaypal(paypalCapture('1.00'));
    $topUp = pendingPaypalTopUp($this->advertiser);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']));

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Failed)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(0);
});

it('does not credit twice when the return url is opened twice', function () {
    fakePaypal();
    pendingPaypalTopUp($this->advertiser);

    $this->actingAs($this->advertiser)->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']));
    $this->actingAs($this->advertiser)->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']));

    expect($this->advertiser->refresh()->balance_micro)->toBe(20_000_000)
        ->and(Transaction::query()->count())->toBe(1);
});

it('does not let another user capture my order', function () {
    fakePaypal();
    $topUp = pendingPaypalTopUp($this->advertiser);
    $other = User::factory()->advertiser()->create();

    $this->actingAs($other)
        ->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']))
        ->assertNotFound();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Pending);
});

it('marks the top up failed when the buyer cancels', function () {
    $topUp = pendingPaypalTopUp($this->advertiser);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.topups.paypal.cancel', ['token' => 'ORDER-123']))
        ->assertRedirect(route('advertiser.wallet'));

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Failed);
});

it('keeps the top up pending while PayPal still processes the capture', function () {
    fakePaypal(paypalCapture('20.00', 'PENDING'));
    $topUp = pendingPaypalTopUp($this->advertiser);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']))
        ->assertRedirect(route('advertiser.wallet'));

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Pending)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(0);
});

it('recovers a payment that PayPal already captured when the capture call failed', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/*/capture' => Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']]], 422),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-123' => Http::response(paypalCapture('20.00')),
    ]);
    $topUp = pendingPaypalTopUp($this->advertiser);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']))
        ->assertRedirect(route('advertiser.wallet'))
        ->assertSessionHas('success');

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Completed)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(20_000_000);
});

it('shows an error instead of crashing when PayPal cannot be reached on capture', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $topUp = pendingPaypalTopUp($this->advertiser);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.topups.paypal.return', ['token' => 'ORDER-123']))
        ->assertRedirect(route('advertiser.wallet'))
        ->assertSessionHas('error');

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Pending);
});

it('shows an error instead of crashing when PayPal cannot be reached on create', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this->actingAs($this->advertiser)
        ->post(route('advertiser.topups.paypal'), ['usd' => '20'])
        ->assertRedirect(route('advertiser.wallet'))
        ->assertSessionHas('error');

    expect(TopUp::query()->count())->toBe(0);
});
