<?php

use App\Models\CpmRate;
use App\Services\Views\RateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the country rate divided by 1000', function () {
    CpmRate::factory()->create(['country' => 'VN', 'level' => 1, 'rate_per_1000_micro' => 4_500_000]);

    expect((new RateResolver)->ratePerViewMicro('VN', 1))->toBe(4500);
});

it('falls back to the XX row for the same level when the country is unknown', function () {
    CpmRate::factory()->create(['country' => 'XX', 'level' => 1, 'rate_per_1000_micro' => 1_500_000]);
    CpmRate::factory()->create(['country' => 'XX', 'level' => 2, 'rate_per_1000_micro' => 2_000_000]);

    expect((new RateResolver)->ratePerViewMicro('ZZ', 2))->toBe(2000);
});

it('does not use a rate of another level', function () {
    CpmRate::factory()->create(['country' => 'VN', 'level' => 1, 'rate_per_1000_micro' => 4_500_000]);
    CpmRate::factory()->create(['country' => 'XX', 'level' => 2, 'rate_per_1000_micro' => 2_000_000]);

    expect((new RateResolver)->ratePerViewMicro('VN', 2))->toBe(2000);
});

it('returns zero when there is no rate at all', function () {
    expect((new RateResolver)->ratePerViewMicro('VN', 1))->toBe(0);
});
