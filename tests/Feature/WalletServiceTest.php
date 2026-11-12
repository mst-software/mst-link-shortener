<?php

use App\Enums\TransactionAction;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->wallet = app(WalletService::class);
});

it('credits the balance and records before and after', function () {
    $user = User::factory()->create(['balance_micro' => 1_000_000]);

    $transaction = $this->wallet->credit($user, 500_000, TransactionAction::ViewEarnings, 'view-1', 'Test credit');

    expect($user->balance_micro)->toBe(1_500_000)
        ->and($transaction->type)->toBe('in')
        ->and($transaction->action)->toBe(TransactionAction::ViewEarnings)
        ->and($transaction->amount_micro)->toBe(500_000)
        ->and($transaction->balance_before_micro)->toBe(1_000_000)
        ->and($transaction->balance_after_micro)->toBe(1_500_000)
        ->and($transaction->reference_id)->toBe('view-1')
        ->and($transaction->description)->toBe('Test credit');
});

it('debits the balance and records an out entry', function () {
    $user = User::factory()->create(['balance_micro' => 1_000_000]);

    $transaction = $this->wallet->debit($user, 400_000, TransactionAction::ReferralReversal);

    expect($user->balance_micro)->toBe(600_000)
        ->and($transaction->type)->toBe('out')
        ->and($transaction->balance_before_micro)->toBe(1_000_000)
        ->and($transaction->balance_after_micro)->toBe(600_000);
});

it('refuses a debit below zero and leaves the balance untouched', function () {
    $user = User::factory()->create(['balance_micro' => 100_000]);

    expect(fn () => $this->wallet->debit($user, 100_001, TransactionAction::EarningsAdjustment))
        ->toThrow(InsufficientBalanceException::class);

    expect($user->fresh()->balance_micro)->toBe(100_000)
        ->and(Transaction::query()->count())->toBe(0);
});

it('reserves money for a withdrawal', function () {
    $user = User::factory()->create(['balance_micro' => 5_000_000]);

    $transaction = $this->wallet->reserveForWithdrawal($user, 2_000_000, 'wd-1');

    $user->refresh();
    expect($user->balance_micro)->toBe(3_000_000)
        ->and($user->processing_micro)->toBe(2_000_000)
        ->and($transaction->action)->toBe(TransactionAction::WithdrawRequest)
        ->and($transaction->reference_id)->toBe('wd-1');
});

it('refuses to reserve more than the available balance', function () {
    $user = User::factory()->create(['balance_micro' => 1_000_000]);

    expect(fn () => $this->wallet->reserveForWithdrawal($user, 1_000_001, 'wd-1'))
        ->toThrow(InsufficientBalanceException::class);

    $user->refresh();
    expect($user->balance_micro)->toBe(1_000_000)
        ->and($user->processing_micro)->toBe(0);
});

it('settles a withdrawal out of the processing balance', function () {
    $user = User::factory()->create(['balance_micro' => 5_000_000]);
    $this->wallet->reserveForWithdrawal($user, 2_000_000, 'wd-1');

    $transaction = $this->wallet->settleWithdrawal($user, 2_000_000, 'wd-1');

    $user->refresh();
    expect($user->balance_micro)->toBe(3_000_000)
        ->and($user->processing_micro)->toBe(0)
        ->and($transaction->action)->toBe(TransactionAction::WithdrawPaid);
});

it('releases a rejected withdrawal back to the available balance', function () {
    $user = User::factory()->create(['balance_micro' => 5_000_000]);
    $this->wallet->reserveForWithdrawal($user, 2_000_000, 'wd-1');

    $transaction = $this->wallet->releaseWithdrawal($user, 2_000_000, 'wd-1');

    $user->refresh();
    expect($user->balance_micro)->toBe(5_000_000)
        ->and($user->processing_micro)->toBe(0)
        ->and($transaction->action)->toBe(TransactionAction::WithdrawRejected);
});

it('refuses to settle or release more than is processing', function () {
    $user = User::factory()->create(['balance_micro' => 1_000_000]);
    $this->wallet->reserveForWithdrawal($user, 500_000, 'wd-1');

    expect(fn () => $this->wallet->settleWithdrawal($user, 500_001, 'wd-1'))
        ->toThrow(InsufficientBalanceException::class);
    expect(fn () => $this->wallet->releaseWithdrawal($user, 500_001, 'wd-1'))
        ->toThrow(InsufficientBalanceException::class);
});

it('keeps the balance equal to the net of the ledger after mixed operations', function () {
    $user = User::factory()->create();

    $this->wallet->credit($user, 10_000_000, TransactionAction::ViewEarnings);
    $this->wallet->credit($user, 2_500_000, TransactionAction::ReferralCommission);
    $this->wallet->debit($user, 1_000_000, TransactionAction::ReferralReversal);
    $this->wallet->reserveForWithdrawal($user, 5_000_000, 'wd-1');
    $this->wallet->settleWithdrawal($user, 5_000_000, 'wd-1');
    $this->wallet->reserveForWithdrawal($user, 3_000_000, 'wd-2');
    $this->wallet->releaseWithdrawal($user, 3_000_000, 'wd-2');

    $net = Transaction::query()->where('user_id', $user->id)->get()
        ->sum(fn (Transaction $t): int => $t->balance_after_micro - $t->balance_before_micro);

    $user->refresh();
    expect($user->balance_micro)->toBe($net)
        ->and($user->balance_micro)->toBe(6_500_000)
        ->and($user->processing_micro)->toBe(0);
});

it('ignores a stale model passed in and works from the stored balance', function () {
    $user = User::factory()->create(['balance_micro' => 1_000_000]);
    $stale = User::query()->find($user->id);

    $this->wallet->credit($user, 1_000_000, TransactionAction::ViewEarnings);
    $transaction = $this->wallet->credit($stale, 500_000, TransactionAction::ViewEarnings);

    expect($transaction->balance_before_micro)->toBe(2_000_000)
        ->and($transaction->balance_after_micro)->toBe(2_500_000)
        ->and($user->fresh()->balance_micro)->toBe(2_500_000)
        ->and($stale->balance_micro)->toBe(2_500_000);
});

it('rejects a non-positive amount', function () {
    $user = User::factory()->create();

    expect(fn () => $this->wallet->credit($user, 0, TransactionAction::ViewEarnings))
        ->toThrow(InvalidArgumentException::class);
});
