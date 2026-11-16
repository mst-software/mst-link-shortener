<?php

use App\Enums\PayoutMethod;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalApproved;
use App\Notifications\WithdrawalRejected;
use App\Notifications\WithdrawalRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

/**
 * A member with a pending $10 PayPal withdrawal and the matching processing balance.
 */
function memberWithPendingWithdrawal(): Withdrawal
{
    $member = User::factory()->create([
        'balance_micro' => 10_000_000,
        'processing_micro' => 10_000_000,
        'payout_method' => PayoutMethod::Paypal,
        'payout_details' => ['paypal_email' => 'me@example.com'],
    ]);

    return Withdrawal::factory()->for($member)->create([
        'amount_micro' => 10_000_000,
        'method' => PayoutMethod::Paypal,
    ]);
}

/**
 * The rendered mail lines of a notification, joined into one string.
 */
function mailText(object $notification, User $notifiable): string
{
    $mail = $notification->toMail($notifiable);

    return implode("\n", [$mail->subject, ...$mail->introLines, ...$mail->outroLines]);
}

test('a member gets a confirmation when their withdrawal request is saved', function () {
    $member = User::factory()->create([
        'balance_micro' => 20_000_000,
        'payout_method' => PayoutMethod::Paypal,
        'payout_details' => ['paypal_email' => 'me@example.com'],
    ]);

    $this->actingAs($member)
        ->post(route('member.withdraws.store'), ['amount' => '10', 'traffic_source' => 'https://youtube.com/@me'])
        ->assertSessionHasNoErrors();

    $withdrawal = Withdrawal::query()->sole();

    Notification::assertSentToTimes($member, WithdrawalRequested::class, 1);
    Notification::assertSentTo($member, WithdrawalRequested::class, function (WithdrawalRequested $notification) use ($member, $withdrawal) {
        expect($notification->withdrawal->is($withdrawal))->toBeTrue()
            ->and(mailText($notification, $member))->toContain('$10.00')->toContain('PayPal');

        return true;
    });
});

test('no confirmation is sent when the withdrawal request fails', function () {
    $member = User::factory()->create([
        'balance_micro' => 8_000_000,
        'payout_method' => PayoutMethod::Paypal,
        'payout_details' => ['paypal_email' => 'me@example.com'],
    ]);

    $this->actingAs($member)
        ->post(route('member.withdraws.store'), ['amount' => '9', 'traffic_source' => 'https://youtube.com/@me'])
        ->assertSessionHasErrors('amount');

    Notification::assertNothingSent();
});

test('the member is emailed once when an admin approves the withdrawal', function () {
    $withdrawal = memberWithPendingWithdrawal();
    $member = $withdrawal->user;
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.withdrawals.approve', $withdrawal))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.withdrawals.approve', $withdrawal))->assertSessionHas('error');

    Notification::assertSentToTimes($member, WithdrawalApproved::class, 1);
    Notification::assertNotSentTo($admin, WithdrawalApproved::class);
    Notification::assertNotSentTo($member, WithdrawalRejected::class);
    Notification::assertSentTo($member, WithdrawalApproved::class, function (WithdrawalApproved $notification) use ($member) {
        expect(mailText($notification, $member))->toContain('$10.00')->toContain('PayPal')->toContain('paid');

        return true;
    });
});

test('the member is emailed the reason when an admin rejects the withdrawal', function () {
    $withdrawal = memberWithPendingWithdrawal();
    $member = $withdrawal->user;
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.withdrawals.reject', $withdrawal), ['admin_note' => 'Invalid traffic source.'])
        ->assertSessionHas('success');

    Notification::assertSentToTimes($member, WithdrawalRejected::class, 1);
    Notification::assertSentTo($member, WithdrawalRejected::class, function (WithdrawalRejected $notification) use ($member) {
        expect(mailText($notification, $member))
            ->toContain('$10.00')
            ->toContain('PayPal')
            ->toContain('Reason: Invalid traffic source.');

        return true;
    });
});

test('no email is sent when the admin action fails', function () {
    $withdrawal = memberWithPendingWithdrawal();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.withdrawals.reject', $withdrawal), ['admin_note' => ''])
        ->assertSessionHasErrors('admin_note');

    $withdrawal->user->forceFill(['processing_micro' => 0])->save();

    $this->actingAs($admin)
        ->post(route('admin.withdrawals.approve', $withdrawal))
        ->assertSessionHas('error');

    Notification::assertNothingSent();
});

test('a processed withdrawal that is rejected later sends no rejection email', function () {
    $withdrawal = memberWithPendingWithdrawal();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.withdrawals.approve', $withdrawal));
    $this->actingAs($admin)
        ->post(route('admin.withdrawals.reject', $withdrawal), ['admin_note' => 'Too late.'])
        ->assertSessionHas('error');

    Notification::assertNotSentTo($withdrawal->user, WithdrawalRejected::class);
});
