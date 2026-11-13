<?php

use App\Enums\ReviewStatus;
use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Models\Campaign;
use App\Models\TopUp;
use App\Models\User;
use App\Models\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores review status price and owner on a campaign', function () {
    $owner = User::factory()->advertiser()->create();

    $campaign = Campaign::factory()->forAdvertiser($owner)->pending()->create(['price_micro' => 15_000])->refresh();

    expect($campaign->owner->is($owner))->toBeTrue()
        ->and($campaign->review_status)->toBe(ReviewStatus::Pending)
        ->and($campaign->price_micro)->toBe(15_000);
});

it('defaults campaigns without an owner to approved', function () {
    $campaign = Campaign::factory()->create()->refresh();

    expect($campaign->user_id)->toBeNull()
        ->and($campaign->review_status)->toBe(ReviewStatus::Approved)
        ->and($campaign->price_micro)->toBeNull();
});

it('stores campaign id and advertiser cost on a view', function () {
    $campaign = Campaign::factory()->create();

    $view = View::factory()->create(['campaign_id' => $campaign->id, 'advertiser_cost_micro' => 10_000])->refresh();

    expect($view->campaign->is($campaign))->toBeTrue()
        ->and($view->advertiser_cost_micro)->toBe(10_000);
});

it('defaults the advertiser cost of a view to zero', function () {
    $view = View::factory()->create()->refresh();

    expect($view->campaign_id)->toBeNull()
        ->and($view->advertiser_cost_micro)->toBe(0);
});

it('keeps a unique provider and reference pair on top ups', function () {
    TopUp::factory()->create(['provider' => TopUpProvider::Sepay, 'reference' => 'LC1ABC']);

    TopUp::factory()->create(['provider' => TopUpProvider::Paypal, 'reference' => 'LC1ABC']);

    expect(fn () => TopUp::factory()->create(['provider' => TopUpProvider::Sepay, 'reference' => 'LC1ABC']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('casts top up fields', function () {
    $topUp = TopUp::factory()->create(['raw' => ['sepay_id' => '99']])->refresh();

    expect($topUp->status)->toBe(TopUpStatus::Pending)
        ->and($topUp->raw)->toBe(['sepay_id' => '99'])
        ->and($topUp->amount_micro)->toBeInt()
        ->and($topUp->user)->toBeInstanceOf(User::class);
});
