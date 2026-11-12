<?php

use App\Enums\PayoutMethod;
use App\Enums\TransactionAction;
use App\Enums\WithdrawalStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A member with a payout method and the given available balance in micro-USD.
 */
function withdrawingMember(int $balanceMicro = 20_000_000, bool $withPayout = true): User
{
    return User::factory()->create([
        'balance_micro' => $balanceMicro,
        'payout_method' => $withPayout ? PayoutMethod::Paypal : null,
        'payout_details' => $withPayout ? ['paypal_email' => 'me@example.com'] : null,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function withdrawalPayload(array $overrides = []): array
{
    return array_merge(['amount' => '10', 'traffic_source' => 'https://youtube.com/@me'], $overrides);
}

test('a valid request reserves the amount and creates a pending withdrawal', function () {
    $member = withdrawingMember();

    $this->actingAs($member)
        ->post(route('member.withdraws.store'), withdrawalPayload())
        ->assertRedirect(route('member.withdraws'))
        ->assertSessionHas('success');

    $member->refresh();
    $withdrawal = Withdrawal::query()->sole();

    expect($member->balance_micro)->toBe(10_000_000)
        ->and($member->processing_micro)->toBe(10_000_000)
        ->and($withdrawal->status)->toBe(WithdrawalStatus::Pending)
        ->and($withdrawal->amount_micro)->toBe(10_000_000)
        ->and($withdrawal->method)->toBe(PayoutMethod::Paypal)
        ->and($withdrawal->details)->toBe(['paypal_email' => 'me@example.com'])
        ->and(Transaction::query()->where('action', TransactionAction::WithdrawRequest)->where('reference_id', (string) $withdrawal->id)->count())->toBe(1);
});

test('a request below the minimum is rejected', function () {
    $member = withdrawingMember();

    $this->actingAs($member)
        ->post(route('member.withdraws.store'), withdrawalPayload(['amount' => '1']))
        ->assertSessionHasErrors('amount');

    expect(Withdrawal::query()->count())->toBe(0)
        ->and($member->fresh()->balance_micro)->toBe(20_000_000);
});

test('a request without a payout method is rejected', function () {
    $member = withdrawingMember(withPayout: false);

    $this->actingAs($member)
        ->post(route('member.withdraws.store'), withdrawalPayload())
        ->assertSessionHasErrors('amount');

    expect(Withdrawal::query()->count())->toBe(0)
        ->and($member->fresh()->balance_micro)->toBe(20_000_000);
});

test('a request larger than the balance is rejected and the balance is unchanged', function () {
    $member = withdrawingMember(8_000_000);

    $this->actingAs($member)
        ->post(route('member.withdraws.store'), withdrawalPayload(['amount' => '9']))
        ->assertSessionHasErrors('amount');

    $member->refresh();

    expect(Withdrawal::query()->count())->toBe(0)
        ->and($member->balance_micro)->toBe(8_000_000)
        ->and($member->processing_micro)->toBe(0);
});

test('two requests that together exceed the balance cannot both succeed', function () {
    $member = withdrawingMember(12_000_000);

    $this->actingAs($member)->post(route('member.withdraws.store'), withdrawalPayload(['amount' => '8']))->assertSessionHasNoErrors();
    $this->actingAs($member)->post(route('member.withdraws.store'), withdrawalPayload(['amount' => '8']))->assertSessionHasErrors('amount');

    $member->refresh();

    expect(Withdrawal::query()->count())->toBe(1)
        ->and($member->balance_micro)->toBe(4_000_000)
        ->and($member->processing_micro)->toBe(8_000_000);
});

test('the traffic source is required', function () {
    $this->actingAs(withdrawingMember())
        ->post(route('member.withdraws.store'), withdrawalPayload(['traffic_source' => '']))
        ->assertSessionHasErrors('traffic_source');
});

test('approving marks the withdrawal paid and updates the total paid box', function () {
    $member = withdrawingMember();
    $this->actingAs($member)->post(route('member.withdraws.store'), withdrawalPayload());
    $withdrawal = Withdrawal::query()->sole();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.withdrawals.approve', $withdrawal))
        ->assertRedirect(route('admin.withdrawals.index'))
        ->assertSessionHas('success');

    $withdrawal->refresh();
    $member->refresh();

    expect($withdrawal->status)->toBe(WithdrawalStatus::Paid)
        ->and($withdrawal->processed_by)->toBe($admin->id)
        ->and($withdrawal->processed_at)->not->toBeNull()
        ->and($member->processing_micro)->toBe(0)
        ->and($member->balance_micro)->toBe(10_000_000);

    $this->actingAs($member)
        ->get(route('member.withdraws'))
        ->assertOk()
        ->assertSee('Total paid')
        ->assertSee('$10.000');
});

test('rejecting returns the money and requires a note', function () {
    $member = withdrawingMember();
    $this->actingAs($member)->post(route('member.withdraws.store'), withdrawalPayload());
    $withdrawal = Withdrawal::query()->sole();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.withdrawals.reject', $withdrawal), ['admin_note' => ''])
        ->assertSessionHasErrors('admin_note');

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Pending);

    $this->actingAs($admin)
        ->post(route('admin.withdrawals.reject', $withdrawal), ['admin_note' => 'Invalid traffic source.'])
        ->assertRedirect(route('admin.withdrawals.index'));

    $withdrawal->refresh();
    $member->refresh();

    expect($withdrawal->status)->toBe(WithdrawalStatus::Rejected)
        ->and($withdrawal->admin_note)->toBe('Invalid traffic source.')
        ->and($member->balance_micro)->toBe(20_000_000)
        ->and($member->processing_micro)->toBe(0);
});

test('approving twice changes nothing the second time', function () {
    $member = withdrawingMember();
    $this->actingAs($member)->post(route('member.withdraws.store'), withdrawalPayload());
    $withdrawal = Withdrawal::query()->sole();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.withdrawals.approve', $withdrawal));
    $transactionCount = Transaction::query()->count();

    $this->actingAs($admin)
        ->post(route('admin.withdrawals.approve', $withdrawal))
        ->assertSessionHas('error');

    expect(Transaction::query()->count())->toBe($transactionCount)
        ->and($member->fresh()->processing_micro)->toBe(0)
        ->and($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Paid);
});

test('a processed withdrawal cannot be rejected afterwards', function () {
    $member = withdrawingMember();
    $this->actingAs($member)->post(route('member.withdraws.store'), withdrawalPayload());
    $withdrawal = Withdrawal::query()->sole();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.withdrawals.approve', $withdrawal));

    $this->actingAs($admin)
        ->post(route('admin.withdrawals.reject', $withdrawal), ['admin_note' => 'Too late.'])
        ->assertSessionHas('error');

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Paid)
        ->and($member->fresh()->balance_micro)->toBe(10_000_000);
});

test('members cannot approve or reject withdrawals', function () {
    $member = withdrawingMember();
    $withdrawal = Withdrawal::factory()->create();

    $this->actingAs($member)->post(route('admin.withdrawals.approve', $withdrawal))->assertForbidden();
    $this->actingAs($member)->post(route('admin.withdrawals.reject', $withdrawal), ['admin_note' => 'No.'])->assertForbidden();
    $this->actingAs($member)->get(route('admin.withdrawals.index'))->assertForbidden();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Pending);
});

test('the admin list can be filtered by status', function () {
    $admin = User::factory()->admin()->create();
    Withdrawal::factory()->create(['traffic_source' => 'pending-source.example']);
    Withdrawal::factory()->paid()->create(['traffic_source' => 'paid-source.example']);

    $this->actingAs($admin)
        ->get(route('admin.withdrawals.index', ['status' => 'paid']))
        ->assertOk()
        ->assertSee('paid-source.example')
        ->assertDontSee('pending-source.example');
});

test('the withdraw button is disabled below the minimum', function () {
    $this->actingAs(withdrawingMember(1_000_000))
        ->get(route('member.withdraws'))
        ->assertOk()
        ->assertSee('disabled="disabled"', false)
        ->assertDontSee('id="withdraw-open"', false);

    $this->actingAs(withdrawingMember(6_000_000))
        ->get(route('member.withdraws'))
        ->assertSee('id="withdraw-open"', false);
});
