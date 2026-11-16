<?php

use App\Enums\TransactionAction;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserLogin;
use App\Models\View;
use App\Services\ReferralCommissionService;
use App\Services\Settings;
use App\Services\Views\EarningsReleaser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A referrer and a referred member who signed up from the same IP with the same browser.
 *
 * @return array{0: User, 1: User}
 */
function sameFootprintPair(string $referredUserAgent = 'Mozilla/5.0 SameBrowser'): array
{
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by' => $referrer->id]);
    UserLogin::factory()->signup()->for($referrer)->withUserAgent('Mozilla/5.0 SameBrowser')->create(['ip' => '198.51.100.50']);
    UserLogin::factory()->signup()->for($referred)->withUserAgent($referredUserAgent)->create(['ip' => '198.51.100.50']);

    return [$referrer, $referred];
}

function releaseEarningsFor(User $user, int $earnMicro): void
{
    View::factory()->create([
        'link_id' => Link::factory()->create(['user_id' => $user->id])->id,
        'user_id' => $user->id,
        'earn_micro' => $earnMicro,
        'matures_at' => now()->subMinute(),
    ]);

    app(EarningsReleaser::class)->release();
}

test('a same-footprint referral raises one flag per pair and still pays when holding is off', function () {
    [$referrer, $referred] = sameFootprintPair();

    releaseEarningsFor($referred, 10_000);
    releaseEarningsFor($referred, 10_000);

    $flag = FraudFlag::query()->sole();

    expect($flag->reason)->toBe(ReferralCommissionService::FLAG_REASON)
        ->and($flag->user_id)->toBe($referrer->id)
        ->and($flag->link_id)->toBeNull()
        ->and($flag->details['referrer_id'])->toBe($referrer->id)
        ->and($flag->details['referred_user_id'])->toBe($referred->id)
        ->and($flag->details['signals'])->toBe(['same_signup_ip', 'same_device', 'referral_relation'])
        ->and($flag->details['held_commission_micro'])->toBe(0)
        ->and($referrer->refresh()->balance_micro)->toBe(2_400);
});

test('a referral sharing only the sign-up ip raises no flag', function () {
    [$referrer, $referred] = sameFootprintPair('Mozilla/5.0 OtherBrowser');

    releaseEarningsFor($referred, 10_000);

    expect(FraudFlag::query()->count())->toBe(0)
        ->and($referrer->refresh()->balance_micro)->toBe(1_200);
});

test('with holding on the commission is kept on the open flag instead of being paid', function () {
    Settings::set('referral_hold_on_suspicion', '1');
    [$referrer, $referred] = sameFootprintPair();

    releaseEarningsFor($referred, 10_000);
    releaseEarningsFor($referred, 5_000);

    expect($referrer->refresh()->balance_micro)->toBe(0)
        ->and(Transaction::query()->where('action', TransactionAction::ReferralCommission)->count())->toBe(0)
        ->and(FraudFlag::query()->sole()->details['held_commission_micro'])->toBe(1_800)
        ->and($referred->refresh()->balance_micro)->toBe(15_000);
});

test('a handled flag lets later commissions be paid even with holding on', function () {
    Settings::set('referral_hold_on_suspicion', '1');
    [$referrer, $referred] = sameFootprintPair();
    releaseEarningsFor($referred, 10_000);

    FraudFlag::query()->sole()->update(['resolved_at' => now()]);
    releaseEarningsFor($referred, 10_000);

    expect($referrer->refresh()->balance_micro)->toBe(1_200)
        ->and(FraudFlag::query()->count())->toBe(1);
});
