<?php

use App\Enums\AdminAction;
use App\Enums\TransactionAction;
use App\Models\AdminAuditLog;
use App\Models\FraudFlag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserLogin;
use App\Services\ReferralCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $details
 */
function multiAccountFlag(User $referrer, User $referred, array $details = []): FraudFlag
{
    return FraudFlag::factory()->create([
        'link_id' => null,
        'user_id' => $referrer->id,
        'reason' => ReferralCommissionService::FLAG_REASON,
        'details' => [
            'referrer_id' => $referrer->id,
            'referred_user_id' => $referred->id,
            'signals' => ['same_signup_ip', 'same_device', 'referral_relation'],
            'score' => 95,
            'shared_ips' => ['198.51.100.60'],
            'held_commission_micro' => 0,
            ...$details,
        ],
    ]);
}

test('the admin user page lists related accounts with reasons, score and escaped names', function () {
    $user = User::factory()->create();
    $twin = User::factory()->create(['username' => '<b>twin</b>', 'referred_by' => $user->id]);
    UserLogin::factory()->signup()->for($user)->withUserAgent('Same browser')->create(['ip' => '198.51.100.60']);
    UserLogin::factory()->signup()->for($twin)->withUserAgent('Same browser')->create(['ip' => '198.51.100.60']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertSee('Related accounts')
        ->assertSee(route('admin.users.show', $twin))
        ->assertSee('&lt;b&gt;twin&lt;/b&gt;', false)
        ->assertDontSee('<b>twin</b>', false)
        ->assertSee('Same sign-up IP, Same browser, Referral between them')
        ->assertSee('95')
        ->assertSee('198.51.100.60');
});

test('the admin user page says when no account is related', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.users.show', User::factory()->create()))
        ->assertOk()
        ->assertSee('No accounts share this user');
});

test('the fraud flags page shows a multi-account referral flag in plain words', function () {
    $referrer = User::factory()->create(['username' => 'boss']);
    $referred = User::factory()->create(['username' => '<i>alt</i>']);
    multiAccountFlag($referrer, $referred, ['held_commission_micro' => 1_200]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.fraud-flags.index'))
        ->assertOk()
        ->assertSee('Multi account referral')
        ->assertSee('&lt;i&gt;alt&lt;/i&gt;', false)
        ->assertDontSee('<i>alt</i>', false)
        ->assertSee('Same sign-up IP')
        ->assertSee('Held commission')
        ->assertSee('Pay held commission');
});

test('an admin can pay the held commission once, which is recorded in the ledger and audit log', function () {
    $admin = User::factory()->admin()->create();
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by' => $referrer->id]);
    $flag = multiAccountFlag($referrer, $referred, ['held_commission_micro' => 1_800]);

    $this->actingAs($admin)->post(route('admin.fraud-flags.pay-held-commission', $flag))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.fraud-flags.pay-held-commission', $flag))->assertSessionHasErrors('flag');

    $transaction = Transaction::query()->where('user_id', $referrer->id)->sole();

    expect($referrer->refresh()->balance_micro)->toBe(1_800)
        ->and($transaction->action)->toBe(TransactionAction::ReferralCommission)
        ->and($transaction->reference_id)->toBe((string) $referred->id)
        ->and($flag->refresh()->resolved_at)->not->toBeNull()
        ->and($flag->details['held_commission_micro'])->toBe(0)
        ->and($flag->details['paid_commission_micro'])->toBe(1_800)
        ->and(AdminAuditLog::query()->sole()->action)->toBe(AdminAction::HeldCommissionPaid);
});

test('members cannot pay held commissions', function () {
    $referrer = User::factory()->create();
    $flag = multiAccountFlag($referrer, User::factory()->create(), ['held_commission_micro' => 1_800]);

    $this->actingAs($referrer)->post(route('admin.fraud-flags.pay-held-commission', $flag))->assertForbidden();

    expect($referrer->refresh()->balance_micro)->toBe(0);
});
