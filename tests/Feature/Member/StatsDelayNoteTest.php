<?php

use App\Models\User;
use App\Services\ScheduleHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-16 10:00:00');
    app(ScheduleHealth::class)->recordSuccess('views:aggregate');
});

it('hides the delay note while stats are aggregated on time', function (): void {
    Carbon::setTestNow(now()->addMinutes(10));

    $this->actingAs(User::factory()->create())
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertDontSee('Stats may be delayed.');
});

it('shows the delay note when stats aggregation is late', function (): void {
    Carbon::setTestNow(now()->addMinutes(11));

    $this->actingAs(User::factory()->create())
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertSee('Stats may be delayed.');
});
