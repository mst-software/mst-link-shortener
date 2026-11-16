<?php

use App\Enums\AdminAction;
use App\Enums\TransactionAction;
use App\Models\AdminAuditLog;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('non admins cannot reach any user management route', function (?string $state) {
    $target = User::factory()->create();
    $routes = [
        ['get', route('admin.users.index'), []],
        ['get', route('admin.users.show', $target), []],
        ['get', route('admin.audit-logs.index'), []],
        ['post', route('admin.users.ban', $target), ['reason' => 'x']],
        ['post', route('admin.users.unban', $target), []],
        ['post', route('admin.users.verify-email', $target), []],
        ['post', route('admin.users.verification-email', $target), []],
        ['post', route('admin.users.password-reset', $target), []],
        ['patch', route('admin.users.role', $target), ['role' => 'admin']],
        ['post', route('admin.users.balance-adjustments.store', $target), ['direction' => 'credit', 'amount' => 5, 'reason' => 'x']],
    ];

    foreach ($routes as [$method, $url, $data]) {
        if ($state === null) {
            $this->{$method}($url, $data)->assertRedirect(route('login'));

            continue;
        }

        $actor = $state === 'advertiser' ? User::factory()->advertiser()->create() : User::factory()->create();
        $this->actingAs($actor)->{$method}($url, $data)->assertForbidden();
    }

    expect($target->refresh()->isActive())->toBeTrue()
        ->and($target->isAdmin())->toBeFalse()
        ->and($target->balance_micro)->toBe(0)
        ->and(AdminAuditLog::query()->count())->toBe(0);
})->with(['guest' => [null], 'member' => ['member'], 'advertiser' => ['advertiser']]);

test('a member cannot make themselves an admin or unban themselves through the profile form', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('member.user.profile.update'), [
        'default_level' => 1,
        'role' => 'admin',
        'status' => 'banned',
        'balance_micro' => 999_000_000,
    ]);

    $member->refresh();

    expect($member->isAdmin())->toBeFalse()
        ->and($member->isActive())->toBeTrue()
        ->and($member->balance_micro)->toBe(0);
});

test('the users list shows newest first with link counts and balances', function () {
    $admin = User::factory()->admin()->create(['username' => 'boss']);
    $older = User::factory()->create(['username' => 'older_user', 'balance_micro' => 12_340_000]);
    Link::factory()->count(3)->create(['user_id' => $older->id]);
    User::factory()->create(['username' => 'newer_user']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSeeInOrder(['newer_user', 'older_user', 'boss'])
        ->assertSee('$12.34')
        ->assertViewHas('users', fn ($users) => $users->firstWhere('id', $older->id)->links_count === 3);
});

test('the users list loads link counts without a query per row', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(10)->has(Link::factory()->count(2))->create();

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    $linkQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"links"'));

    expect($linkQueries)->toHaveCount(1);
});

test('the users list can be searched by username, email or id', function () {
    $admin = User::factory()->admin()->create();
    $alice = User::factory()->create(['username' => 'alice_cash', 'email' => 'alice@example.test']);
    $bob = User::factory()->create(['username' => 'bob_links', 'email' => 'bob@example.test']);

    $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'alice_c']))
        ->assertSee('alice_cash')->assertDontSee('bob_links');

    $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'bob@example']))
        ->assertSee('bob_links')->assertDontSee('alice_cash');

    $this->actingAs($admin)->get(route('admin.users.index', ['q' => (string) $alice->id]))
        ->assertViewHas('users', fn ($users) => $users->contains('id', $alice->id) && ! $users->contains('id', $bob->id));
});

test('the users list filters by role, status, verification and join date', function () {
    $admin = User::factory()->admin()->create(['username' => 'boss']);
    User::factory()->advertiser()->create(['username' => 'ad_buyer']);
    User::factory()->banned()->create(['username' => 'bad_actor']);
    User::factory()->unverified()->create(['username' => 'new_comer']);
    User::factory()->create(['username' => 'old_timer', 'created_at' => now()->subMonths(2)]);

    $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'advertiser']))
        ->assertSee('ad_buyer')->assertDontSee('bad_actor')->assertDontSee('new_comer');

    $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'banned']))
        ->assertSee('bad_actor')->assertDontSee('ad_buyer');

    $this->actingAs($admin)->get(route('admin.users.index', ['verified' => 'no']))
        ->assertSee('new_comer')->assertDontSee('bad_actor');

    $this->actingAs($admin)->get(route('admin.users.index', ['joined' => '7_days']))
        ->assertSee('new_comer')->assertDontSee('old_timer');

    $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'owner']))
        ->assertSessionHasErrors('role');
});

test('the user page shows balances, ledger, withdrawals, links, referrals, fraud flags and the audit log', function () {
    $admin = User::factory()->admin()->create(['username' => 'boss']);
    $referrer = User::factory()->create(['username' => 'the_referrer']);
    $user = User::factory()->create([
        'username' => 'target_user',
        'referred_by' => $referrer->id,
        'balance_micro' => 7_500_000,
        'processing_micro' => 2_000_000,
    ]);
    User::factory()->create(['username' => 'invited_friend', 'referred_by' => $user->id]);
    $link = Link::factory()->create(['user_id' => $user->id, 'alias' => 'FlagMe1']);
    $link->forceFill(['views_count' => 42])->save();
    Link::factory()->banned()->create(['user_id' => $user->id]);
    Transaction::factory()->create([
        'user_id' => $user->id,
        'action' => TransactionAction::ReferralCommission,
        'amount_micro' => 250_000,
        'description' => 'Referral commission for release 9',
    ]);
    Withdrawal::factory()->create(['user_id' => $user->id, 'amount_micro' => 15_000_000]);
    FraudFlag::factory()->create(['link_id' => $link->id, 'reason' => 'ip_burst']);
    AdminAuditLog::factory()->create(['admin_id' => $admin->id, 'target_user_id' => $user->id, 'reason' => 'Spamming links']);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertSee('$7.500000')
        ->assertSee('$2.000000')
        ->assertSee('the_referrer')
        ->assertSee('invited_friend')
        ->assertSee('Referral commission for release 9')
        ->assertSee('$0.250000')
        ->assertSee('$15.00')
        ->assertSee('2 (1 banned)')
        ->assertSee('42')
        ->assertSee('ip_burst')
        ->assertSee('FlagMe1')
        ->assertSee('Spamming links')
        ->assertSee(AdminAction::UserBanned->label());
});

test('the audit log page lists entries and filters them', function () {
    $admin = User::factory()->admin()->create(['username' => 'boss']);
    $other = User::factory()->admin()->create(['username' => 'helper']);
    $banned = User::factory()->create(['username' => 'spammer']);
    $promoted = User::factory()->create(['username' => 'trusted']);

    AdminAuditLog::factory()->create(['admin_id' => $admin->id, 'target_user_id' => $banned->id, 'action' => AdminAction::UserBanned, 'reason' => 'Spam wave']);
    AdminAuditLog::factory()->create(['admin_id' => $other->id, 'target_user_id' => $promoted->id, 'action' => AdminAction::RoleChanged, 'reason' => 'New moderator']);
    AdminAuditLog::factory()->create([
        'admin_id' => $admin->id,
        'target_user_id' => $promoted->id,
        'action' => AdminAction::BalanceAdjusted,
        'reason' => 'Old bonus',
        'created_at' => now()->subMonths(2),
    ]);

    $this->actingAs($admin)->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertSee('Spam wave')->assertSee('New moderator')->assertSee('Old bonus');

    $this->actingAs($admin)->get(route('admin.audit-logs.index', ['action' => 'role_changed']))
        ->assertSee('New moderator')->assertDontSee('Spam wave');

    $this->actingAs($admin)->get(route('admin.audit-logs.index', ['admin' => 'helper']))
        ->assertSee('New moderator')->assertDontSee('Spam wave');

    $this->actingAs($admin)->get(route('admin.audit-logs.index', ['q' => 'spammer']))
        ->assertSee('Spam wave')->assertDontSee('New moderator');

    $this->actingAs($admin)->get(route('admin.audit-logs.index', ['time_range' => '7_days']))
        ->assertSee('Spam wave')->assertDontSee('Old bonus');

    $this->actingAs($admin)->get(route('admin.audit-logs.index', ['from' => now()->subMonths(3)->toDateString(), 'to' => now()->subMonth()->toDateString()]))
        ->assertSee('Old bonus')->assertDontSee('Spam wave');
});
