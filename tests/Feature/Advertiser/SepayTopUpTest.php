<?php

use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Enums\TransactionAction;
use App\Models\TopUp;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Advertiser\TopUpService;
use App\Services\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.sepay.api_token' => 'test-token']);
    Settings::set('sepay_account_number', '0123456789');
    Settings::set('sepay_bank_name', 'MBBank');
    $this->advertiser = User::factory()->advertiser()->create();
});

/**
 * Fake the SePay transaction list with the given incoming transactions.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function fakeSepay(array $rows): void
{
    Http::fake(['my.sepay.vn/userapi/transactions/list*' => Http::response([
        'status' => 200,
        'messages' => ['success' => true],
        'transactions' => $rows,
    ])]);
}

/**
 * @return array<string, mixed>
 */
function sepayRow(string $id, string $content, string $amountIn = '250000.00', string $amountOut = '0.00'): array
{
    return [
        'id' => $id,
        'transaction_date' => '2026-11-13 10:00:00',
        'account_number' => '0123456789',
        'amount_in' => $amountIn,
        'amount_out' => $amountOut,
        'transaction_content' => $content,
        'reference_number' => 'FT123',
    ];
}

it('creates a pending top up with a locked rate and a unique reference', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);

    expect($topUp->provider)->toBe(TopUpProvider::Sepay)
        ->and($topUp->status)->toBe(TopUpStatus::Pending)
        ->and($topUp->amount_micro)->toBe(10_000_000)
        ->and($topUp->original_amount)->toBe(250_000)
        ->and($topUp->original_currency)->toBe('VND')
        ->and($topUp->exchange_rate)->toBe(25_000)
        ->and($topUp->reference)->toStartWith('LC'.$this->advertiser->id)
        ->and($topUp->expires_at->isFuture())->toBeTrue();

    Settings::set('vnd_per_usd', 30_000);

    expect($topUp->refresh()->amount_micro)->toBe(10_000_000)
        ->and($topUp->sepayQrUrl())->toContain('acc=0123456789')->toContain('amount=250000')->toContain('des='.$topUp->reference);
});

it('refuses amounts below the minimum', function () {
    // The default minimum is 5 USD, which is 125,000 VND at 25,000 VND per USD.
    expect(fn () => app(TopUpService::class)->createSepay($this->advertiser, 124_999))
        ->toThrow(InvalidArgumentException::class);

    $this->actingAs($this->advertiser)
        ->post(route('advertiser.topups.sepay'), ['vnd' => 124_999])
        ->assertSessionHasErrors('vnd');

    expect(TopUp::query()->count())->toBe(0);
});

it('creates the top up from the wallet form', function () {
    $this->actingAs($this->advertiser)
        ->post(route('advertiser.topups.sepay'), ['vnd' => 250_000])
        ->assertRedirect(route('advertiser.wallet'));

    expect(TopUp::query()->sole()->user_id)->toBe($this->advertiser->id);
});

it('credits the wallet when a matching incoming transaction is synced', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9001', 'MBVCB.123.'.$topUp->reference.'.CT tu 0001')]);

    $this->artisan('sepay:sync')->assertSuccessful();

    $topUp->refresh();
    $transaction = Transaction::query()->where('user_id', $this->advertiser->id)->sole();

    expect($topUp->status)->toBe(TopUpStatus::Completed)
        ->and($topUp->completed_at)->not->toBeNull()
        ->and($topUp->raw['sepay_id'])->toBe('9001')
        ->and($this->advertiser->refresh()->balance_micro)->toBe(10_000_000)
        ->and($transaction->action)->toBe(TransactionAction::Topup)
        ->and($transaction->type)->toBe('in')
        ->and($transaction->balance_before_micro)->toBe(0)
        ->and($transaction->balance_after_micro)->toBe(10_000_000);
});

it('matches the reference without regard to letter case', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9002', strtolower($topUp->reference))]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Completed);
});

it('credits only once when the sync runs twice', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9001', $topUp->reference)]);

    $this->artisan('sepay:sync')->assertSuccessful();
    $this->artisan('sepay:sync')->assertSuccessful();

    expect($this->advertiser->refresh()->balance_micro)->toBe(10_000_000)
        ->and(Transaction::query()->count())->toBe(1);
});

it('credits only once when the same payment is listed twice in one sync', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9001', $topUp->reference), sepayRow('9001', $topUp->reference)]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($this->advertiser->refresh()->balance_micro)->toBe(10_000_000);
});

it('ignores outgoing transactions', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9003', $topUp->reference, '0.00', '250000.00')]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Pending)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(0);
});

it('marks a wrong amount as mismatch without crediting', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9004', $topUp->reference, '200000.00')]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Mismatch)
        ->and($topUp->raw['sepay_id'])->toBe('9004')
        ->and($this->advertiser->refresh()->balance_micro)->toBe(0);
});

it('credits a payment that is synced shortly after the top up expired', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    $topUp->update(['expires_at' => now()->subHour()]);
    fakeSepay([sepayRow('9007', $topUp->reference)]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Completed)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(10_000_000);
});

it('marks a payment that arrives long after expiry as mismatch without crediting', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    $topUp->update(['expires_at' => now()->subHours(13)]);
    fakeSepay([sepayRow('9005', $topUp->reference)]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Mismatch)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(0);
});

it('marks unpaid expired top ups as failed once the grace period is over', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    $topUp->update(['expires_at' => now()->subHours(13)]);
    fakeSepay([]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Failed);
});

it('ignores transactions with unknown references', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9006', 'salary for november')]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Pending)
        ->and($this->advertiser->refresh()->balance_micro)->toBe(0);
});

it('does not throw and credits nothing when the SePay api fails', function () {
    $topUp = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    Http::fake(['my.sepay.vn/*' => Http::response('server error', 500)]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($topUp->refresh()->status)->toBe(TopUpStatus::Pending);
});

it('does not call SePay when no token is set', function () {
    config(['services.sepay.api_token' => null]);
    Http::fake();

    $this->artisan('sepay:sync')->assertSuccessful();

    Http::assertNothingSent();
});

it('is scheduled every minute', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'sepay:sync'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');
});

it('credits one top up only when a single transfer names two references', function () {
    $first = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    $second = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9010', $first->reference.' '.$second->reference)]);

    $this->artisan('sepay:sync')->assertSuccessful();
    $this->artisan('sepay:sync')->assertSuccessful();

    $statuses = collect([$first->refresh()->status, $second->refresh()->status]);

    expect($this->advertiser->refresh()->balance_micro)->toBe(10_000_000)
        ->and($statuses->filter(fn (TopUpStatus $status): bool => $status === TopUpStatus::Completed))->toHaveCount(1)
        ->and($statuses->filter(fn (TopUpStatus $status): bool => $status === TopUpStatus::Pending))->toHaveCount(1);
});

it('never uses the same bank transaction for another top up', function () {
    $first = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    $second = app(TopUpService::class)->createSepay($this->advertiser, 250_000);
    fakeSepay([sepayRow('9011', $first->reference), sepayRow('9011', $second->reference)]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($first->refresh()->status)->toBe(TopUpStatus::Completed)
        ->and($second->refresh()->status)->toBe(TopUpStatus::Pending)
        ->and($first->provider_transaction_id)->toBe('9011')
        ->and($this->advertiser->refresh()->balance_micro)->toBe(10_000_000);
});

it('prefers the longest matching reference', function () {
    $short = TopUp::factory()->create(['user_id' => $this->advertiser->id, 'reference' => 'LC12ABCDE']);
    $long = TopUp::factory()->create(['user_id' => $this->advertiser->id, 'reference' => 'LC12ABCDEF']);
    fakeSepay([sepayRow('9012', 'MBVCB LC12ABCDEF thanks')]);

    $this->artisan('sepay:sync')->assertSuccessful();

    expect($long->refresh()->status)->toBe(TopUpStatus::Completed)
        ->and($short->refresh()->status)->toBe(TopUpStatus::Pending);
});
