<?php

use App\Enums\ViewStatus;
use App\Enums\VisitStatus;
use App\Models\BlockedNetwork;
use App\Models\CpmRate;
use App\Models\FraudFlag;
use App\Models\View;
use App\Models\ViewDedup;
use App\Models\VisitSession;
use Database\Seeders\CpmRateSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a row from every factory', function () {
    $rate = CpmRate::factory()->create();
    $session = VisitSession::factory()->create();
    $view = View::factory()->create();
    $dedup = ViewDedup::factory()->create();
    $network = BlockedNetwork::factory()->create();
    $flag = FraudFlag::factory()->create();

    expect($rate->exists)->toBeTrue()
        ->and($session->status)->toBe(VisitStatus::InProgress)
        ->and($session->getKey())->toHaveLength(26)
        ->and($view->status)->toBe(ViewStatus::Pending)
        ->and($view->visitSession->link_id)->toBe($view->link_id)
        ->and($view->user_id)->toBe($view->link->user_id)
        ->and($dedup->exists)->toBeTrue()
        ->and($network->exists)->toBeTrue()
        ->and($flag->details)->toBeArray();
});

it('keeps country and level unique in cpm_rates', function () {
    CpmRate::factory()->create(['country' => 'VN', 'level' => 1]);

    CpmRate::factory()->create(['country' => 'VN', 'level' => 1]);
})->throws(UniqueConstraintViolationException::class);

it('keeps view_dedup.key_hash unique', function () {
    $hash = hash('sha256', 'visitor');
    ViewDedup::factory()->create(['key_hash' => $hash]);

    ViewDedup::factory()->create(['key_hash' => $hash]);
})->throws(UniqueConstraintViolationException::class);

it('allows only one view per visit session', function () {
    $view = View::factory()->create();

    View::factory()->create([
        'link_id' => $view->link_id,
        'visit_session_id' => $view->visit_session_id,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('seeds 13 countries times 3 levels of rates', function () {
    $this->seed(CpmRateSeeder::class);

    expect(CpmRate::query()->count())->toBe(39)
        ->and(CpmRate::query()->distinct()->count('country'))->toBe(13)
        ->and(CpmRate::query()->where('country', 'VN')->where('level', 2)->value('rate_per_1000_micro'))->toBe(6_000_000)
        ->and(CpmRate::query()->where('country', 'XX')->where('level', 3)->value('rate_per_1000_micro'))->toBe(2_500_000);
});

it('seeds rates idempotently', function () {
    $this->seed(CpmRateSeeder::class);
    $this->seed(CpmRateSeeder::class);

    expect(CpmRate::query()->count())->toBe(39);
});
