<?php

use App\Enums\InvalidReason;
use App\Enums\TransactionAction;
use App\Enums\ViewStatus;
use App\Enums\VisitMode;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\Link;
use App\Models\Transaction;
use App\Models\User;
use App\Models\View;
use App\Models\VisitSession;
use App\Services\Advertiser\AdvertiserBilling;
use App\Services\LinkBanService;
use App\Services\Settings;
use App\Services\Views\ViewRecorder;
use App\Services\Views\VisitorContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Record the view of a fresh keyword session for the campaign, from a unique visitor.
 */
function recordKeywordView(Campaign $campaign, ?InvalidReason $reason = null, ?Link $link = null): View
{
    $session = VisitSession::factory()->create([
        'link_id' => ($link ?? Link::factory()->create())->getKey(),
        'mode' => VisitMode::Keyword,
        'campaign_id' => $campaign->getKey(),
        'fingerprint' => fake()->unique()->md5(),
    ]);

    $context = new VisitorContext($session->ip, $session->ua_hash, 'XX', 'test-agent');

    return app(ViewRecorder::class)->record($session, $context, $reason);
}

it('charges the campaign owner for a valid keyword view', function () {
    $owner = User::factory()->advertiser()->create(['balance_micro' => 1_000_000]);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);

    $view = recordKeywordView($campaign);

    $transaction = Transaction::query()->where('user_id', $owner->id)->sole();

    expect($owner->refresh()->balance_micro)->toBe(990_000)
        ->and($view->campaign_id)->toBe($campaign->id)
        ->and($view->advertiser_cost_micro)->toBe(10_000)
        ->and($transaction->action)->toBe(TransactionAction::CampaignCharge)
        ->and($transaction->type)->toBe('out')
        ->and($transaction->amount_micro)->toBe(10_000);
});

it('uses the default price when the campaign has none', function () {
    Settings::set('advertiser_default_price_micro', 25_000);
    $owner = User::factory()->advertiser()->create(['balance_micro' => 1_000_000]);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => null]);

    expect(app(AdvertiserBilling::class)->pricePerViewMicro($campaign))->toBe(25_000);

    recordKeywordView($campaign);

    expect($owner->refresh()->balance_micro)->toBe(975_000);
});

it('does not charge an invalid view but still stores the campaign id', function () {
    $owner = User::factory()->advertiser()->create(['balance_micro' => 1_000_000]);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);

    $view = recordKeywordView($campaign, InvalidReason::Bot);

    expect($view->status)->toBe(ViewStatus::Invalid)
        ->and($view->campaign_id)->toBe($campaign->id)
        ->and($view->advertiser_cost_micro)->toBe(0)
        ->and($owner->refresh()->balance_micro)->toBe(1_000_000);
});

it('does not charge admin campaigns without an owner', function () {
    $campaign = Campaign::factory()->create();

    $view = recordKeywordView($campaign);

    expect($view->campaign_id)->toBe($campaign->id)
        ->and($view->advertiser_cost_micro)->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});

it('records a free counted view and never goes negative when the wallet cannot pay', function () {
    $owner = User::factory()->advertiser()->create(['balance_micro' => 5_000]);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);

    $view = recordKeywordView($campaign);

    expect($view->status)->toBe(ViewStatus::Pending)
        ->and($view->advertiser_cost_micro)->toBe(0)
        ->and($owner->refresh()->balance_micro)->toBe(5_000);
});

it('charges only the views the wallet can pay for', function () {
    $owner = User::factory()->advertiser()->create(['balance_micro' => 10_000]);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);

    $first = recordKeywordView($campaign);
    $second = recordKeywordView($campaign);

    expect($first->advertiser_cost_micro)->toBe(10_000)
        ->and($second->advertiser_cost_micro)->toBe(0)
        ->and($owner->refresh()->balance_micro)->toBe(0);
});

it('tells whether the owner can pay one more view', function () {
    $billing = app(AdvertiserBilling::class);
    $owner = User::factory()->advertiser()->create(['balance_micro' => 9_999]);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);

    expect($billing->canAfford($campaign))->toBeFalse()
        ->and($billing->canAfford(Campaign::factory()->create()))->toBeTrue();

    $owner->forceFill(['balance_micro' => 10_000])->save();

    expect($billing->canAfford($campaign->refresh()))->toBeTrue();
});

it('refunds once when the same cancelled view is refunded twice', function () {
    $owner = User::factory()->advertiser()->create(['balance_micro' => 1_000_000]);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);
    $view = recordKeywordView($campaign);
    $billing = app(AdvertiserBilling::class);

    $billing->refundViews([$view]);
    $billing->refundViews([$view->refresh()]);

    expect($owner->refresh()->balance_micro)->toBe(1_000_000)
        ->and(Transaction::query()->where('action', TransactionAction::ChargeRefund)->count())->toBe(1);
});

it('refunds the advertiser when a link ban cancels pending views', function () {
    $owner = User::factory()->advertiser()->create(['balance_micro' => 1_000_000]);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);
    $link = Link::factory()->create();
    $view = recordKeywordView($campaign, null, $link);

    expect($owner->refresh()->balance_micro)->toBe(990_000);

    app(LinkBanService::class)->ban($link, 'abuse');

    expect($view->refresh()->status)->toBe(ViewStatus::Cancelled)
        ->and($owner->refresh()->balance_micro)->toBe(1_000_000)
        ->and(Transaction::query()->where('action', TransactionAction::ChargeRefund)->count())->toBe(1);
});

it('keeps views that belong to an advertiser campaign when purging', function () {
    $campaign = Campaign::factory()->forAdvertiser()->create();
    $kept = View::factory()->create(['status' => ViewStatus::Invalid, 'campaign_id' => $campaign->id, 'created_at' => now()->subYear()]);
    $dropped = View::factory()->create(['status' => ViewStatus::Invalid, 'created_at' => now()->subYear()]);

    $this->artisan('views:purge')->assertSuccessful();

    expect(View::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and(View::query()->whereKey($dropped->id)->exists())->toBeFalse();
});

it('purges views of admin campaigns but keeps views and code attempts of advertiser campaigns', function () {
    $adminCampaign = Campaign::factory()->create();
    $advertiserCampaign = Campaign::factory()->forAdvertiser()->create();
    $adminView = View::factory()->create(['status' => ViewStatus::Invalid, 'campaign_id' => $adminCampaign->id, 'created_at' => now()->subYear()]);
    $keptView = View::factory()->create(['status' => ViewStatus::Invalid, 'campaign_id' => $advertiserCampaign->id, 'created_at' => now()->subYear()]);
    $adminAttempt = CodeAttempt::factory()->issued()->create(['campaign_id' => $adminCampaign->id, 'expires_at' => now()->subWeek()]);
    $keptAttempt = CodeAttempt::factory()->issued()->create(['campaign_id' => $advertiserCampaign->id, 'expires_at' => now()->subWeek()]);

    $this->artisan('views:purge')->assertSuccessful();

    expect(View::query()->whereKey($adminView->id)->exists())->toBeFalse()
        ->and(View::query()->whereKey($keptView->id)->exists())->toBeTrue()
        ->and(CodeAttempt::query()->whereKey($adminAttempt->id)->exists())->toBeFalse()
        ->and(CodeAttempt::query()->whereKey($keptAttempt->id)->exists())->toBeTrue();
});
