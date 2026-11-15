<?php

use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Enums\VisitMode;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\User;
use App\Models\VisitSession;
use App\Services\Views\VisitFlow;
use App\Services\Views\VisitorContext;
use App\Services\Visits\CodeRedeemer;
use App\Services\Visits\CodeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fundedAdvertiser(int $balance = 1_000_000): User
{
    return User::factory()->advertiser()->create(['balance_micro' => $balance]);
}

it('hides pending and rejected advertiser campaigns', function () {
    $owner = fundedAdvertiser();
    $pending = Campaign::factory()->forAdvertiser($owner)->pending()->create();
    $rejected = Campaign::factory()->forAdvertiser($owner)->create(['review_status' => ReviewStatus::Rejected]);
    $approved = Campaign::factory()->forAdvertiser($owner)->create();

    $ids = Campaign::query()->available()->pluck('id');

    expect($ids)->toContain($approved->id)
        ->and($ids)->not->toContain($pending->id)
        ->and($ids)->not->toContain($rejected->id);
});

it('hides campaigns whose owner is locked', function () {
    $owner = fundedAdvertiser();
    $campaign = Campaign::factory()->forAdvertiser($owner)->create();

    expect(Campaign::query()->available()->whereKey($campaign->id)->exists())->toBeTrue();

    $owner->forceFill(['status' => UserStatus::Banned])->save();

    expect(Campaign::query()->available()->whereKey($campaign->id)->exists())->toBeFalse();
});

it('hides campaigns whose owner cannot pay one view', function () {
    $campaign = Campaign::factory()->forAdvertiser(fundedAdvertiser(9_999))->create(['price_micro' => 10_000]);
    $defaultPriced = Campaign::factory()->forAdvertiser(fundedAdvertiser(10_000))->create(['price_micro' => null]);

    $ids = Campaign::query()->available()->pluck('id');

    expect($ids)->not->toContain($campaign->id)
        ->and($ids)->toContain($defaultPriced->id);
});

it('keeps admin campaigns available', function () {
    $campaign = Campaign::factory()->create();

    expect(Campaign::query()->available()->whereKey($campaign->id)->exists())->toBeTrue();
});

it('shows the campaign again after the owner tops up', function () {
    $owner = fundedAdvertiser(0);
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);

    expect(Campaign::query()->available()->whereKey($campaign->id)->exists())->toBeFalse();

    $owner->forceFill(['balance_micro' => 50_000])->save();

    expect(Campaign::query()->available()->whereKey($campaign->id)->exists())->toBeTrue();
});

/**
 * A keyword session of the campaign whose device already holds an unused code.
 *
 * @return array{0: VisitSession, 1: CodeAttempt}
 */
function sessionHoldingCode(Campaign $campaign): array
{
    $session = VisitSession::factory()->create([
        'mode' => VisitMode::Keyword,
        'campaign_id' => $campaign->id,
        'created_at' => now()->subMinutes(5),
    ]);

    $attempt = CodeAttempt::factory()->issued()->create([
        'campaign_id' => $campaign->id,
        'ua_hash' => $session->ua_hash,
        'ip' => $session->ip,
        'code' => 'abc234',
    ]);

    return [$session, $attempt];
}

it('moves a session to wait mode and refuses the issued code once the campaign is sent back to review', function () {
    $campaign = Campaign::factory()->forAdvertiser(fundedAdvertiser())->create();
    [$session, $attempt] = sessionHoldingCode($campaign);

    $campaign->update(['review_status' => ReviewStatus::Pending]);

    expect(app(VisitFlow::class)->downgradeToWaitIfUnavailable($session))->toBeTrue()
        ->and($session->refresh()->mode)->toBe(VisitMode::Wait);

    $session->forceFill(['mode' => VisitMode::Keyword])->save();
    $context = new VisitorContext($session->ip, $session->ua_hash, 'XX', 'agent');

    expect(app(CodeRedeemer::class)->redeem($session, 'abc234', $context))->toBe(CodeResult::Invalid)
        ->and($attempt->refresh()->used_at)->toBeNull();
});

it('moves a session to wait mode when the owner is locked', function () {
    $owner = fundedAdvertiser();
    $campaign = Campaign::factory()->forAdvertiser($owner)->create();
    [$session] = sessionHoldingCode($campaign);

    $owner->forceFill(['status' => UserStatus::Banned])->save();

    expect(app(VisitFlow::class)->downgradeToWaitIfUnavailable($session))->toBeTrue();
});

it('lets a visitor finish with an issued code when the wallet ran out meanwhile', function () {
    $owner = fundedAdvertiser();
    $campaign = Campaign::factory()->forAdvertiser($owner)->create(['price_micro' => 10_000]);
    [$session, $attempt] = sessionHoldingCode($campaign);

    $owner->forceFill(['balance_micro' => 0])->save();
    $context = new VisitorContext($session->ip, $session->ua_hash, 'XX', 'agent');

    expect(app(VisitFlow::class)->downgradeToWaitIfUnavailable($session))->toBeFalse()
        ->and(app(CodeRedeemer::class)->redeem($session, 'abc234', $context))->toBe(CodeResult::Accepted);
});
