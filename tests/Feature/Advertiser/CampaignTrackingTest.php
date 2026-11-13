<?php

use App\Enums\InvalidReason;
use App\Enums\ViewStatus;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\User;
use App\Models\View;
use App\Services\Advertiser\CampaignReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->advertiser = User::factory()->advertiser()->create(['balance_micro' => 5_000_000]);
    $this->campaign = Campaign::factory()->forAdvertiser($this->advertiser)->create(['price_micro' => 10_000]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function campaignView(Campaign $campaign, ViewStatus $status, array $attributes = []): View
{
    return View::factory()->create(array_merge([
        'campaign_id' => $campaign->id,
        'status' => $status,
        'advertiser_cost_micro' => in_array($status, [ViewStatus::Pending, ViewStatus::Released], true) ? 10_000 : 0,
    ], $attributes));
}

it('counts the funnel steps for a date range', function () {
    CodeAttempt::factory()->issued()->count(2)->create(['campaign_id' => $this->campaign->id, 'started_at' => now()]);
    CodeAttempt::factory()->create(['campaign_id' => $this->campaign->id, 'started_at' => now()]);
    CodeAttempt::factory()->issued()->create(['campaign_id' => $this->campaign->id, 'started_at' => now()->subDays(40), 'issued_at' => now()->subDays(40)]);
    campaignView($this->campaign, ViewStatus::Pending);
    campaignView($this->campaign, ViewStatus::Invalid, ['invalid_reason' => InvalidReason::Bot]);
    campaignView($this->campaign, ViewStatus::Cancelled);
    campaignView($this->campaign, ViewStatus::Pending, ['created_at' => now()->subDays(40)]);

    $funnel = (new CampaignReport($this->campaign))->funnel(now()->subDays(29)->startOfDay(), now()->endOfDay());

    expect($funnel)->toBe([
        'started' => 3,
        'issued' => 2,
        'counted' => 1,
        'not_counted' => 2,
        'spent_micro' => 10_000,
    ]);
});

it('builds a daily series with the days that had no traffic', function () {
    CodeAttempt::factory()->issued()->create(['campaign_id' => $this->campaign->id, 'started_at' => now(), 'issued_at' => now()]);
    campaignView($this->campaign, ViewStatus::Pending);

    $series = (new CampaignReport($this->campaign))->daily(now()->subDays(2)->startOfDay(), now()->endOfDay());

    expect($series)->toHaveCount(3)
        ->and($series[0])->toBe(['date' => now()->subDays(2)->toDateString(), 'started' => 0, 'issued' => 0, 'counted' => 0])
        ->and($series[2])->toBe(['date' => now()->toDateString(), 'started' => 1, 'issued' => 1, 'counted' => 1]);
});

it('shows the funnel and the spend on the campaign page', function () {
    campaignView($this->campaign, ViewStatus::Pending);
    campaignView($this->campaign, ViewStatus::Pending);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.campaigns.show', $this->campaign))
        ->assertOk()
        ->assertSee('Started')
        ->assertSee('Code issued')
        ->assertSee('Used - Counted')
        ->assertSee('Used - Not counted')
        ->assertSee('$0.02')
        ->assertSee('$5.00');
});

it('shows not counted visits to the advertiser as Not counted only', function () {
    campaignView($this->campaign, ViewStatus::Invalid, ['invalid_reason' => InvalidReason::BlockedNetwork, 'country' => 'JP']);
    campaignView($this->campaign, ViewStatus::Cancelled, ['country' => 'DE']);

    $response = $this->actingAs($this->advertiser)->get(route('advertiser.campaigns.show', $this->campaign));

    $response->assertSee('<td>JP</td>', false)->assertSee('<td>DE</td>', false);
    expect(substr_count($response->getContent(), 'Not counted'))->toBeGreaterThanOrEqual(2);
});

it('never renders an invalid reason, full ip or fingerprint', function () {
    $view = campaignView($this->campaign, ViewStatus::Invalid, [
        'invalid_reason' => InvalidReason::BlockedNetwork,
        'ip' => '203.0.113.7',
    ]);
    $view->visitSession->forceFill(['fingerprint' => 'fp-secret-0123456789'])->save();

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.campaigns.show', $this->campaign))
        ->assertOk()
        ->assertDontSee('Blocked network')
        ->assertDontSee('blocked_network')
        ->assertDontSee('203.0.113.7')
        ->assertDontSee('fp-secret-0123456789');
});

it('filters visits by status', function () {
    campaignView($this->campaign, ViewStatus::Pending, ['country' => 'FR']);
    campaignView($this->campaign, ViewStatus::Invalid, ['country' => 'JP']);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.campaigns.show', [$this->campaign, 'status' => 'counted']))
        ->assertSee('<td>FR</td>', false)
        ->assertDontSee('<td>JP</td>', false);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.campaigns.show', [$this->campaign, 'status' => 'not_counted']))
        ->assertSee('<td>JP</td>', false)
        ->assertDontSee('<td>FR</td>', false);
});

it('does not list visits of other campaigns', function () {
    $other = Campaign::factory()->forAdvertiser($this->advertiser)->create();
    campaignView($other, ViewStatus::Pending, ['country' => 'FR']);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.campaigns.show', $this->campaign))
        ->assertDontSee('<td>FR</td>', false);
});

it('returns 404 for another advertiser campaign', function () {
    $other = Campaign::factory()->forAdvertiser()->create();

    $this->actingAs($this->advertiser)->get(route('advertiser.campaigns.show', $other))->assertNotFound();
});

it('shows the insufficient balance notice', function () {
    $this->advertiser->forceFill(['balance_micro' => 0])->save();

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.campaigns.show', $this->campaign))
        ->assertSee('Paused: insufficient balance');
});

it('shows the embed snippet only to approved campaigns', function () {
    $pending = Campaign::factory()->forAdvertiser($this->advertiser)->pending()->create();

    $this->actingAs($this->advertiser)->get(route('advertiser.campaigns.show', $this->campaign))
        ->assertSee($this->campaign->widget_key);
    $this->actingAs($this->advertiser)->get(route('advertiser.campaigns.show', $pending))
        ->assertDontSee($pending->widget_key);
});

it('rejects an invalid date range', function () {
    $this->actingAs($this->advertiser)
        ->get(route('advertiser.campaigns.show', [$this->campaign, 'from' => 'nonsense']))
        ->assertSessionHasErrors('from');
});
