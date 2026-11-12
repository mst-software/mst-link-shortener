<?php

use App\Enums\TransactionAction;
use App\Enums\ViewStatus;
use App\Models\DailyStat;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Models\View;
use App\Services\Views\EarningsReleaser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function matureView(User $user, int $earn, array $attributes = []): View
{
    $link = Link::factory()->create(['user_id' => $user->id]);

    return View::factory()->create(array_merge([
        'link_id' => $link->id,
        'user_id' => $user->id,
        'earn_micro' => $earn,
        'matures_at' => now()->subMinute(),
    ], $attributes));
}

it('releases matured views and credits the total in one transaction per user', function () {
    $user = User::factory()->create(['balance_micro' => 100]);
    matureView($user, 1_000);
    matureView($user, 2_000);
    matureView($user, 3_000);

    $released = app(EarningsReleaser::class)->release();

    $transactions = Transaction::query()->where('user_id', $user->id)->where('action', TransactionAction::ViewEarnings)->get();

    expect($released)->toBe(3)
        ->and($transactions)->toHaveCount(1)
        ->and($transactions->first()->amount_micro)->toBe(6_000)
        ->and($user->refresh()->balance_micro)->toBe(6_100)
        ->and(View::query()->where('status', ViewStatus::Released)->count())->toBe(3);
});

it('creates one earnings transaction for each user', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    matureView($first, 1_000);
    matureView($second, 2_000);

    $this->artisan('earnings:release')->assertSuccessful();

    expect(Transaction::query()->where('action', TransactionAction::ViewEarnings)->count())->toBe(2)
        ->and($first->refresh()->balance_micro)->toBe(1_000)
        ->and($second->refresh()->balance_micro)->toBe(2_000);
});

it('pays the referrer a separate commission without reducing the referred user', function () {
    $referrer = User::factory()->create();
    $user = User::factory()->create(['referred_by' => $referrer->id]);
    matureView($user, 10_000);

    app(EarningsReleaser::class)->release();

    $commission = Transaction::query()->where('user_id', $referrer->id)->sole();

    expect($user->refresh()->balance_micro)->toBe(10_000)
        ->and($referrer->refresh()->balance_micro)->toBe(1_200)
        ->and($commission->action)->toBe(TransactionAction::ReferralCommission)
        ->and($commission->amount_micro)->toBe(1_200)
        ->and($commission->reference_id)->toBe((string) $user->id)
        ->and(DailyStat::query()->where('user_id', $referrer->id)->sole()->ref_earn_micro)->toBe(1_200);
});

it('never releases invalid or cancelled views', function () {
    $user = User::factory()->create();
    matureView($user, 5_000, ['status' => ViewStatus::Invalid]);
    matureView($user, 5_000, ['status' => ViewStatus::Cancelled]);

    $released = app(EarningsReleaser::class)->release();

    expect($released)->toBe(0)
        ->and($user->refresh()->balance_micro)->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});

it('keeps views that have not reached matures_at pending', function () {
    $user = User::factory()->create();
    matureView($user, 5_000, ['matures_at' => now()->addHour()]);

    $released = app(EarningsReleaser::class)->release();

    expect($released)->toBe(0)
        ->and($user->refresh()->balance_micro)->toBe(0)
        ->and(View::query()->where('status', ViewStatus::Pending)->count())->toBe(1);
});

it('does not pay twice when release runs twice', function () {
    $referrer = User::factory()->create();
    $user = User::factory()->create(['referred_by' => $referrer->id]);
    matureView($user, 10_000);

    app(EarningsReleaser::class)->release();
    $second = app(EarningsReleaser::class)->release();

    expect($second)->toBe(0)
        ->and($user->refresh()->balance_micro)->toBe(10_000)
        ->and($referrer->refresh()->balance_micro)->toBe(1_200)
        ->and(Transaction::query()->count())->toBe(2);
});
