<?php

use App\Enums\AdminAction;
use App\Enums\LinkStatus;
use App\Enums\TransactionAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\ViewStatus;
use App\Models\AdminAuditLog;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Models\View;
use App\Services\UserAccountService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['username' => 'boss']);
});

test('banning a user writes an audit row and blocks sign in', function () {
    $user = User::factory()->create(['username' => 'spammer', 'password' => 'secret-pass']);
    $link = Link::factory()->create(['user_id' => $user->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.ban', $user), ['reason' => 'Spam traffic'])
        ->assertRedirect(route('admin.users.show', $user))
        ->assertSessionHas('success', 'User banned.');

    expect($user->refresh()->status)->toBe(UserStatus::Banned)
        ->and($link->refresh()->status)->toBe(LinkStatus::Active);

    $log = AdminAuditLog::query()->sole();
    expect($log->action)->toBe(AdminAction::UserBanned)
        ->and($log->admin_id)->toBe($this->admin->id)
        ->and($log->target_user_id)->toBe($user->id)
        ->and($log->reason)->toBe('Spam traffic')
        ->and($log->old_values)->toBe(['status' => 'active'])
        ->and($log->new_values)->toBe(['status' => 'banned', 'links_banned' => 0])
        ->and($log->ip_address)->toBe('127.0.0.1');

    auth()->logout();

    $this->post(route('login'), ['username' => 'spammer', 'password' => 'secret-pass'])
        ->assertSessionHasErrors(['username' => 'This account is disabled.']);
    $this->assertGuest();
});

test('a ban needs a reason', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.ban', $user), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($user->refresh()->isActive())->toBeTrue();
});

test('banning a user signs out their existing sessions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('member.dashboard'))->assertOk();

    app(UserAccountService::class)->ban($this->admin, $user, 'Fraud');

    $this->actingAs($user->refresh())
        ->get(route('member.dashboard'))
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

test('banning a user removes their stored database sessions', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $bystander = User::factory()->create();

    foreach ([$user, $user, $bystander] as $index => $owner) {
        DB::table('sessions')->insert([
            'id' => 'session-'.$index,
            'user_id' => $owner->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    app(UserAccountService::class)->ban($this->admin, $user, 'Fraud');

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $bystander->id)->count())->toBe(1);
});

test('banning a user can also ban their links and take back released earnings', function () {
    $user = User::factory()->create(['balance_micro' => 3_000_000]);
    $link = Link::factory()->create(['user_id' => $user->id]);
    $alreadyBanned = Link::factory()->banned()->create(['user_id' => $user->id]);
    $pending = View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Pending, 'earn_micro' => 5_000]);
    View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Released, 'earn_micro' => 1_000_000]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.ban', $user), ['reason' => 'Bot traffic', 'ban_links' => '1'])
        ->assertSessionHasNoErrors();

    expect($link->refresh()->status)->toBe(LinkStatus::Banned)
        ->and($alreadyBanned->refresh()->status)->toBe(LinkStatus::Banned)
        ->and($pending->refresh()->status)->toBe(ViewStatus::Cancelled)
        ->and($user->refresh()->balance_micro)->toBe(2_000_000)
        ->and(Transaction::query()->where('user_id', $user->id)->where('action', TransactionAction::EarningsAdjustment)->value('amount_micro'))->toBe(1_000_000)
        ->and(AdminAuditLog::query()->sole()->new_values['links_banned'])->toBe(1);
});

test('unbanning lets the user sign in again and is audited', function () {
    $user = User::factory()->banned()->create(['username' => 'returning', 'password' => 'secret-pass']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.unban', $user), ['reason' => 'Appeal accepted'])
        ->assertSessionHas('success', 'User unbanned.');

    expect($user->refresh()->isActive())->toBeTrue();

    $log = AdminAuditLog::query()->sole();
    expect($log->action)->toBe(AdminAction::UserUnbanned)
        ->and($log->old_values)->toBe(['status' => 'banned'])
        ->and($log->new_values)->toBe(['status' => 'active']);

    auth()->logout();

    $this->post(route('login'), ['username' => 'returning', 'password' => 'secret-pass']);
    $this->assertAuthenticatedAs($user);
});

test('unbanning an active user is refused', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.unban', $user))
        ->assertSessionHasErrors('user');

    expect(AdminAuditLog::query()->count())->toBe(0);
});

test('an admin cannot ban or demote themselves', function () {
    User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.ban', $this->admin), ['reason' => 'Oops'])
        ->assertSessionHasErrors(['user' => 'You cannot ban your own account.']);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.role', $this->admin), ['role' => 'member'])
        ->assertSessionHasErrors(['user' => 'You cannot change your own role.']);

    $this->admin->refresh();
    expect($this->admin->isAdmin())->toBeTrue()
        ->and($this->admin->isActive())->toBeTrue()
        ->and(AdminAuditLog::query()->count())->toBe(0);
});

test('the last active admin cannot be banned or demoted', function () {
    $formerAdmin = User::factory()->admin()->banned()->create();
    $accounts = app(UserAccountService::class);

    expect(fn () => $accounts->ban($formerAdmin, $this->admin, 'Takeover'))
        ->toThrow(ValidationException::class, 'The last active admin cannot be banned.')
        ->and(fn () => $accounts->changeRole($formerAdmin, $this->admin, UserRole::Member))
        ->toThrow(ValidationException::class, 'The last active admin cannot be demoted.');

    $this->admin->refresh();
    expect($this->admin->isAdmin())->toBeTrue()
        ->and($this->admin->isActive())->toBeTrue();
});

test('an admin can be demoted while another active admin remains', function () {
    $colleague = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.users.role', $colleague), ['role' => 'member', 'reason' => 'Left the team'])
        ->assertSessionHas('success', 'Role changed to Member.');

    expect($colleague->refresh()->role)->toBe(UserRole::Member);

    $log = AdminAuditLog::query()->sole();
    expect($log->action)->toBe(AdminAction::RoleChanged)
        ->and($log->old_values)->toBe(['role' => 'admin'])
        ->and($log->new_values)->toBe(['role' => 'member'])
        ->and($log->reason)->toBe('Left the team');
});

test('a member can be promoted to admin', function () {
    $member = User::factory()->create();

    $this->actingAs($this->admin)->patch(route('admin.users.role', $member), ['role' => 'admin']);

    expect($member->refresh()->isAdmin())->toBeTrue();
});

test('roles other than member and admin cannot be set, and advertisers cannot be changed', function () {
    $member = User::factory()->create();
    $advertiser = User::factory()->advertiser()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.users.role', $member), ['role' => 'advertiser'])
        ->assertSessionHasErrors('role');

    $this->actingAs($this->admin)
        ->patch(route('admin.users.role', $advertiser), ['role' => 'admin'])
        ->assertSessionHasErrors(['role' => 'Advertiser accounts cannot change role.']);

    expect($member->refresh()->role)->toBe(UserRole::Member)
        ->and($advertiser->refresh()->role)->toBe(UserRole::Advertiser);
});

test('a credit goes through the ledger and is audited', function () {
    $user = User::factory()->create(['balance_micro' => 1_000_000]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.balance-adjustments.store', $user), ['direction' => 'credit', 'amount' => '2.5', 'reason' => 'Missed payout'])
        ->assertSessionHas('success', 'Balance credited by $2.500000. New balance: $3.500000.');

    $transaction = Transaction::query()->sole();
    expect($user->refresh()->balance_micro)->toBe(3_500_000)
        ->and($transaction->action)->toBe(TransactionAction::AdminAdjustment)
        ->and($transaction->type)->toBe('in')
        ->and($transaction->amount_micro)->toBe(2_500_000)
        ->and($transaction->balance_before_micro)->toBe(1_000_000)
        ->and($transaction->balance_after_micro)->toBe(3_500_000)
        ->and($transaction->reference_id)->toBe('admin:'.$this->admin->id)
        ->and($transaction->description)->toBe('Admin adjustment: Missed payout');

    $log = AdminAuditLog::query()->sole();
    expect($log->action)->toBe(AdminAction::BalanceAdjusted)
        ->and($log->old_values)->toBe(['balance_micro' => 1_000_000])
        ->and($log->new_values)->toBe(['balance_micro' => 3_500_000, 'amount' => '+$2.500000', 'transaction_id' => $transaction->id]);
});

test('a debit goes through the ledger', function () {
    $user = User::factory()->create(['balance_micro' => 1_000_000]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.balance-adjustments.store', $user), ['direction' => 'debit', 'amount' => '0.25', 'reason' => 'Duplicate credit']);

    expect($user->refresh()->balance_micro)->toBe(750_000)
        ->and(Transaction::query()->sole()->type)->toBe('out');
});

test('a debit larger than the balance is refused and changes nothing', function () {
    $user = User::factory()->create(['balance_micro' => 1_000_000]);

    $this->actingAs($this->admin)
        ->post(route('admin.users.balance-adjustments.store', $user), ['direction' => 'debit', 'amount' => '1.000001', 'reason' => 'Too much'])
        ->assertSessionHasErrors('amount');

    expect($user->refresh()->balance_micro)->toBe(1_000_000)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(AdminAuditLog::query()->count())->toBe(0);
});

test('a balance adjustment needs a positive amount and a reason', function (array $input, string $field) {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.balance-adjustments.store', $user), [...['direction' => 'credit', 'amount' => '1', 'reason' => 'Bonus'], ...$input])
        ->assertSessionHasErrors($field);

    expect(Transaction::query()->count())->toBe(0);
})->with([
    'no reason' => [['reason' => ''], 'reason'],
    'zero amount' => [['amount' => '0'], 'amount'],
    'negative amount' => [['amount' => '-5'], 'amount'],
    'too many decimals' => [['amount' => '0.0000001'], 'amount'],
    'unknown direction' => [['direction' => 'set'], 'direction'],
]);

test('balance adjustment amounts are checked at their limits', function (string $amount, ?int $creditedMicro) {
    $user = User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post(route('admin.users.balance-adjustments.store', $user), ['direction' => 'credit', 'amount' => $amount, 'reason' => 'Limit check']);

    if ($creditedMicro === null) {
        $response->assertSessionHasErrors('amount');
        expect(Transaction::query()->count())->toBe(0);

        return;
    }

    $response->assertSessionHasNoErrors();
    expect($user->refresh()->balance_micro)->toBe($creditedMicro)
        ->and(Transaction::query()->sole()->amount_micro)->toBe($creditedMicro);
})->with([
    'maximum' => ['100000', 100_000_000_000],
    'just above the maximum' => ['100000.000001', null],
    'minimum' => ['0.000001', 1],
    'seven decimals' => ['1.0000001', null],
]);

test('an admin can mark an email verified', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.verify-email', $user))
        ->assertSessionHas('success', 'Email address marked as verified.');

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and(AdminAuditLog::query()->sole()->action)->toBe(AdminAction::EmailVerified);

    $this->actingAs($this->admin)
        ->post(route('admin.users.verify-email', $user))
        ->assertSessionHasErrors('user');
});

test('an admin can resend the verification email', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $verified = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.verification-email', $user))
        ->assertSessionHas('success', 'Verification email sent.');

    $this->actingAs($this->admin)
        ->post(route('admin.users.verification-email', $verified))
        ->assertSessionHasErrors('user');

    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    Notification::assertNotSentTo($verified, VerifyEmail::class);
    expect(AdminAuditLog::query()->sole()->action)->toBe(AdminAction::VerificationEmailSent);
});

test('an admin can send a password reset link', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.password-reset', $user))
        ->assertSessionHas('success', 'Password reset link sent.');

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
    expect(AdminAuditLog::query()->sole()->action)->toBe(AdminAction::PasswordResetSent);
});
