<?php

use App\Enums\VisitStatus;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\VisitSession;
use App\Services\Views\VisitorContext;
use App\Services\Visits\CodeRedeemer;
use App\Services\Visits\CodeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campaign = Campaign::factory()->create();
    $this->uaHash = hash('sha256', 'redeemer-agent');
    $this->context = new VisitorContext('203.0.113.9', $this->uaHash, 'XX', 'redeemer-agent');
    $this->session = VisitSession::factory()->create([
        'mode' => 'keyword',
        'campaign_id' => $this->campaign->id,
        'ua_hash' => $this->uaHash,
        'ip' => '203.0.113.9',
    ]);
    $this->redeemer = new CodeRedeemer;
});

function attemptFor(Campaign $campaign, array $overrides = []): CodeAttempt
{
    return CodeAttempt::factory()->issued()->create(array_merge([
        'campaign_id' => $campaign->id,
        'code' => 'abc234',
        'ua_hash' => test()->uaHash,
        'ip' => '203.0.113.9',
    ], $overrides));
}

it('accepts a matching code once and links it to the session', function () {
    $attempt = attemptFor($this->campaign);

    expect($this->redeemer->redeem($this->session, 'abc234', $this->context))->toBe(CodeResult::Accepted)
        ->and($attempt->fresh()->visit_session_id)->toBe($this->session->id)
        ->and($attempt->fresh()->used_at)->not->toBeNull()
        ->and($this->session->fresh()->ip_changed)->toBeFalse();

    $other = VisitSession::factory()->create(['mode' => 'keyword', 'campaign_id' => $this->campaign->id, 'ua_hash' => $this->uaHash]);

    expect($this->redeemer->redeem($other, 'abc234', $this->context))->toBe(CodeResult::Invalid);
});

it('is idempotent for the session that already redeemed the code', function () {
    attemptFor($this->campaign);

    $this->redeemer->redeem($this->session, 'abc234', $this->context);

    expect($this->redeemer->redeem($this->session, 'abc234', $this->context))->toBe(CodeResult::Accepted)
        ->and($this->session->fresh()->code_failures)->toBe(0);
});

it('ignores the case and spaces of the typed code', function () {
    attemptFor($this->campaign);

    expect($this->redeemer->redeem($this->session, '  ABC234 ', $this->context))->toBe(CodeResult::Accepted);
});

it('does not find a code of another campaign', function () {
    $attempt = attemptFor(Campaign::factory()->create());

    expect($this->redeemer->redeem($this->session, 'abc234', $this->context))->toBe(CodeResult::Invalid)
        ->and($attempt->fresh()->used_at)->toBeNull();
});

it('refuses a code of another user agent and keeps it usable', function () {
    $attempt = attemptFor($this->campaign, ['ua_hash' => hash('sha256', 'someone else')]);

    expect($this->redeemer->redeem($this->session, 'abc234', $this->context))->toBe(CodeResult::WrongDevice)
        ->and($attempt->fresh()->used_at)->toBeNull()
        ->and($this->session->fresh()->code_failures)->toBe(1);
});

it('refuses an expired code', function () {
    attemptFor($this->campaign, ['expires_at' => now()->subMinute()]);

    expect($this->redeemer->redeem($this->session, 'abc234', $this->context))->toBe(CodeResult::Expired);
});

it('flags the session when the code was issued to another IP', function () {
    attemptFor($this->campaign, ['ip' => '198.51.100.5']);

    expect($this->redeemer->redeem($this->session, 'abc234', $this->context))->toBe(CodeResult::Accepted)
        ->and($this->session->fresh()->ip_changed)->toBeTrue();
});

it('locks the session on the fifth failure', function () {
    foreach (range(1, 4) as $ignored) {
        expect($this->redeemer->redeem($this->session, 'nope11', $this->context))->toBe(CodeResult::Invalid);
    }

    expect($this->redeemer->redeem($this->session, 'nope11', $this->context))->toBe(CodeResult::Locked)
        ->and($this->session->fresh()->status)->toBe(VisitStatus::Failed)
        ->and(CodeRedeemer::failuresLeft($this->session->fresh()))->toBe(0);
});

it('locks an IP after ten failures per hour on a campaign', function () {
    foreach (range(1, 10) as $ignored) {
        $session = VisitSession::factory()->create(['mode' => 'keyword', 'campaign_id' => $this->campaign->id, 'ua_hash' => $this->uaHash]);
        $this->redeemer->redeem($session, 'nope11', $this->context);
    }

    attemptFor($this->campaign);

    expect($this->redeemer->redeem($this->session, 'abc234', $this->context))->toBe(CodeResult::Locked);
});
