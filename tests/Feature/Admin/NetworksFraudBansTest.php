<?php

use App\Enums\LinkStatus;
use App\Enums\TransactionAction;
use App\Enums\ViewStatus;
use App\Models\BlockedNetwork;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Models\View;
use App\Services\LinkBanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('importing networks skips invalid lines and reports the counts', function () {
    $path = tempnam(sys_get_temp_dir(), 'nets');
    file_put_contents($path, "10.0.0.0/8 # internal\n\nnot-an-ip\n203.0.113.0/24\n2001:db8::/32 # docs\n10.1.0.0/99\n");

    $this->artisan('networks:import', ['file' => $path])
        ->expectsOutput('Imported 3 networks, skipped 2 invalid lines.')
        ->assertSuccessful();

    expect(BlockedNetwork::query()->count())->toBe(3)
        ->and(BlockedNetwork::query()->where('cidr', '10.0.0.0/8')->value('label'))->toBe('internal');

    unlink($path);
});

test('importing a duplicate cidr replaces its label', function () {
    BlockedNetwork::factory()->create(['cidr' => '10.0.0.0/8', 'label' => 'old']);
    $path = tempnam(sys_get_temp_dir(), 'nets');
    file_put_contents($path, "10.0.0.0/8 # new\n");

    $this->artisan('networks:import', ['file' => $path])->assertSuccessful();

    expect(BlockedNetwork::query()->count())->toBe(1)
        ->and(BlockedNetwork::query()->value('label'))->toBe('new');

    unlink($path);
});

test('admins can add, relabel and remove a blocked network', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.blocked-networks.store'), ['cidr' => '198.51.100.0/24', 'label' => 'test'])
        ->assertRedirect(route('admin.blocked-networks.index'));

    $network = BlockedNetwork::query()->sole();

    $this->actingAs($admin)->put(route('admin.blocked-networks.update', $network), ['label' => 'renamed']);
    expect($network->refresh()->label)->toBe('renamed');

    $this->actingAs($admin)->post(route('admin.blocked-networks.store'), ['cidr' => 'nope'])->assertSessionHasErrors('cidr');

    $this->actingAs($admin)->delete(route('admin.blocked-networks.destroy', $network));
    expect(BlockedNetwork::query()->count())->toBe(0);
});

test('a banned link returns 404 at the short url', function () {
    Link::factory()->banned()->create(['alias' => 'Banned1']);

    $this->get('/Banned1')->assertNotFound();
});

test('banning a link cancels pending views and reverses released earnings in the ledger', function () {
    $referrer = User::factory()->create(['balance_micro' => 1_000_000]);
    $owner = User::factory()->create(['balance_micro' => 5_000_000, 'referred_by' => $referrer->id]);
    $link = Link::factory()->create(['user_id' => $owner->id]);

    $pending = View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Pending, 'earn_micro' => 2_000]);
    View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Released, 'earn_micro' => 1_000_000]);
    View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Released, 'earn_micro' => 1_000_000]);

    app(LinkBanService::class)->ban($link, 'Fraud');

    // Referral percent defaults to 12, so the referrer gives back 12% of the 2,000,000 reversed.
    expect($link->refresh()->status)->toBe(LinkStatus::Banned)
        ->and($pending->refresh()->status)->toBe(ViewStatus::Cancelled)
        ->and($owner->refresh()->balance_micro)->toBe(3_000_000)
        ->and($referrer->refresh()->balance_micro)->toBe(760_000);

    $adjustment = Transaction::query()->where('user_id', $owner->id)->where('action', TransactionAction::EarningsAdjustment)->sole();
    $reversal = Transaction::query()->where('user_id', $referrer->id)->where('action', TransactionAction::ReferralReversal)->sole();

    expect($adjustment->type)->toBe('out')
        ->and($adjustment->amount_micro)->toBe(2_000_000)
        ->and($adjustment->balance_before_micro)->toBe(5_000_000)
        ->and($adjustment->balance_after_micro)->toBe(3_000_000)
        ->and($reversal->amount_micro)->toBe(240_000)
        ->and($reversal->balance_after_micro)->toBe(760_000);
});

test('banning twice never reverses twice', function () {
    $owner = User::factory()->create(['balance_micro' => 5_000_000]);
    $link = Link::factory()->create(['user_id' => $owner->id]);
    View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Released, 'earn_micro' => 1_000_000]);

    app(LinkBanService::class)->ban($link, 'Fraud');
    app(LinkBanService::class)->ban($link, 'Fraud again');

    expect($owner->refresh()->balance_micro)->toBe(4_000_000)
        ->and(Transaction::query()->where('action', TransactionAction::EarningsAdjustment)->count())->toBe(1);
});

test('a reversal never takes more than the owner has left', function () {
    $owner = User::factory()->create(['balance_micro' => 300_000]);
    $link = Link::factory()->create(['user_id' => $owner->id]);
    View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Released, 'earn_micro' => 1_000_000]);

    app(LinkBanService::class)->ban($link, 'Fraud');

    expect($owner->refresh()->balance_micro)->toBe(0)
        ->and(Transaction::query()->where('action', TransactionAction::EarningsAdjustment)->sole()->amount_micro)->toBe(300_000);
});

test('the fraud scan flags an ip above the threshold once per day', function () {
    $link = Link::factory()->create();
    View::factory()->count(21)->create(['link_id' => $link->id, 'ip' => '198.51.100.7', 'status' => ViewStatus::Pending]);
    View::factory()->count(20)->create(['link_id' => $link->id, 'ip' => '198.51.100.8', 'status' => ViewStatus::Pending]);

    $this->artisan('fraud:scan')->assertSuccessful();
    $this->artisan('fraud:scan')->assertSuccessful();

    $flag = FraudFlag::query()->sole();

    expect($flag->reason)->toBe('ip_flood')
        ->and($flag->link_id)->toBe($link->id)
        ->and($flag->user_id)->toBe($link->user_id)
        ->and($flag->details['ip'])->toBe('198.51.100.7');
});

test('the fraud scan flags a member with a high invalid ratio', function () {
    $link = Link::factory()->create();
    View::factory()->count(31)->create(['link_id' => $link->id, 'status' => ViewStatus::Invalid]);
    View::factory()->count(19)->create(['link_id' => $link->id, 'status' => ViewStatus::Pending]);

    $this->artisan('fraud:scan')->assertSuccessful();

    expect(FraudFlag::query()->where('reason', 'invalid_ratio')->where('user_id', $link->user_id)->count())->toBe(1);
});

test('admins see visitor reports in the fraud page and can resolve or ban from it', function () {
    $admin = User::factory()->admin()->create();
    $link = Link::factory()->create(['alias' => 'ReportMe']);
    $flag = FraudFlag::factory()->create(['link_id' => $link->id, 'reason' => 'visitor_report', 'details' => ['reason' => 'code_not_shown']]);

    $this->actingAs($admin)->get(route('admin.fraud-flags.index'))
        ->assertOk()
        ->assertSee('Visitor report')
        ->assertSee('ReportMe');

    $this->actingAs($admin)->post(route('admin.fraud-flags.ban-link', $flag), ['reason' => 'Bad link'])->assertRedirect();

    expect($link->refresh()->status)->toBe(LinkStatus::Banned)
        ->and($flag->refresh()->resolved_at)->not->toBeNull();

    $other = FraudFlag::factory()->create();
    $this->actingAs($admin)->patch(route('admin.fraud-flags.resolve', $other))->assertRedirect();

    expect($other->refresh()->resolved_at)->not->toBeNull();
});

test('admins can ban a link from the links page', function () {
    $link = Link::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.links.ban', $link), ['reason' => 'Terms violation'])
        ->assertRedirect();

    expect($link->refresh()->status)->toBe(LinkStatus::Banned);
});

test('members get a 403 on the security routes', function () {
    $member = User::factory()->create();
    $link = Link::factory()->create();
    $flag = FraudFlag::factory()->create();
    $network = BlockedNetwork::factory()->create();

    $this->actingAs($member)->get(route('admin.blocked-networks.index'))->assertForbidden();
    $this->actingAs($member)->delete(route('admin.blocked-networks.destroy', $network))->assertForbidden();
    $this->actingAs($member)->get(route('admin.fraud-flags.index'))->assertForbidden();
    $this->actingAs($member)->patch(route('admin.fraud-flags.resolve', $flag))->assertForbidden();
    $this->actingAs($member)->get(route('admin.links.index'))->assertForbidden();
    $this->actingAs($member)->post(route('admin.links.ban', $link), ['reason' => 'x'])->assertForbidden();
});
