<?php

use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\VisitSession;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates campaigns and code attempts with factories', function (): void {
    $attempt = CodeAttempt::factory()->issued()->create();

    expect($attempt->campaign)->toBeInstanceOf(Campaign::class)
        ->and($attempt->id)->toBeString()->toHaveLength(26)
        ->and($attempt->code)->toHaveLength(6)
        ->and($attempt->page_load_ids)->toBeArray();
});

it('stores keyword fields on visit sessions with wait as the default mode', function (): void {
    $session = VisitSession::factory()->create()->refresh();

    expect($session->mode->value)->toBe('wait')
        ->and($session->campaign_id)->toBeNull()
        ->and($session->code_failures)->toBe(0);

    $campaign = Campaign::factory()->create();
    $attempt = CodeAttempt::factory()->issued()->create(['campaign_id' => $campaign->id, 'visit_session_id' => $session->id]);

    expect($session->codeAttempts)->toHaveCount(1)
        ->and($attempt->visitSession->is($session))->toBeTrue();
});

it('rejects a duplicate code within one campaign but allows it in another', function (): void {
    $campaign = Campaign::factory()->create();
    CodeAttempt::factory()->create(['campaign_id' => $campaign->id, 'code' => 'abc234']);

    CodeAttempt::factory()->create(['campaign_id' => Campaign::factory(), 'code' => 'abc234']);

    expect(fn () => CodeAttempt::factory()->create(['campaign_id' => $campaign->id, 'code' => 'abc234']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows many attempts without a code in one campaign', function (): void {
    $campaign = Campaign::factory()->create();

    CodeAttempt::factory()->count(2)->create(['campaign_id' => $campaign->id]);

    expect($campaign->attempts()->count())->toBe(2);
});

it('limits available campaigns to active ones inside their date window', function (): void {
    $open = Campaign::factory()->create();
    $windowed = Campaign::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
    Campaign::factory()->inactive()->create();
    Campaign::factory()->create(['starts_at' => now()->addHour()]);
    Campaign::factory()->create(['ends_at' => now()->subHour()]);

    expect(Campaign::query()->available()->pluck('id')->all())
        ->toEqualCanonicalizing([$open->id, $windowed->id]);
});

it('counts only the codes issued today', function (): void {
    $campaign = Campaign::factory()->create();
    CodeAttempt::factory()->issued()->count(2)->create(['campaign_id' => $campaign->id]);
    CodeAttempt::factory()->issued()->create(['campaign_id' => $campaign->id, 'issued_at' => now()->subDays(2)]);
    CodeAttempt::factory()->create(['campaign_id' => $campaign->id]);

    expect($campaign->issuedToday())->toBe(2);
});

it('marks an exhausted campaign as having reached its daily limit', function (): void {
    $campaign = Campaign::factory()->exhausted()->create();

    expect($campaign->issuedToday())->toBeGreaterThanOrEqual($campaign->daily_limit);
});
