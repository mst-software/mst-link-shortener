<?php

use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Services\Settings;
use App\Services\Visits\CampaignSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns null when there is no campaign', function () {
    expect((new CampaignSelector)->pick())->toBeNull();
});

it('returns null in wait mode', function () {
    Campaign::factory()->create();
    Settings::set('visit_mode', 'wait');

    expect((new CampaignSelector)->pick())->toBeNull();
});

it('picks an available campaign in auto and keyword mode', function () {
    $campaign = Campaign::factory()->create();

    foreach (['auto', 'keyword'] as $mode) {
        Settings::set('visit_mode', $mode);

        expect((new CampaignSelector)->pick()?->id)->toBe($campaign->id);
    }
});

it('skips inactive, not started, ended and zero weight campaigns', function () {
    Campaign::factory()->inactive()->create();
    Campaign::factory()->create(['starts_at' => now()->addDay()]);
    Campaign::factory()->create(['ends_at' => now()->subDay()]);
    Campaign::factory()->create(['weight' => 0]);

    expect((new CampaignSelector)->pick())->toBeNull();
});

it('skips a campaign that reached its daily limit but not one with a limit of zero', function () {
    Campaign::factory()->exhausted()->create();
    $unlimited = Campaign::factory()->create(['daily_limit' => 0]);

    foreach (range(1, 10) as $ignored) {
        expect((new CampaignSelector)->pick()?->id)->toBe($unlimited->id);
    }
});

it('does not count codes issued on a previous day against the daily limit', function () {
    $campaign = Campaign::factory()->create(['daily_limit' => 1]);
    CodeAttempt::factory()->issued()->create(['campaign_id' => $campaign->id, 'issued_at' => now()->subDays(2)]);

    expect((new CampaignSelector)->pick()?->id)->toBe($campaign->id);
});

it('picks campaigns in proportion to their weight', function () {
    $heavy = Campaign::factory()->create(['weight' => 9]);
    $light = Campaign::factory()->create(['weight' => 1]);

    $picks = collect(range(1, 400))->map(fn () => (new CampaignSelector)->pick()?->id)->countBy();

    expect($picks[$heavy->id])->toBeGreaterThan($picks[$light->id] ?? 0)
        ->and($picks->keys()->all())->each->toBeIn([$heavy->id, $light->id]);
});
