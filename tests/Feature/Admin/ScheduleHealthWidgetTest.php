<?php

use App\Models\User;
use App\Services\ScheduleHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-16 10:00:00');
});

it('shows the status of every scheduled job', function (): void {
    $health = app(ScheduleHealth::class);
    $health->recordSuccess('views:aggregate');
    $health->recordSuccess('earnings:release');
    Carbon::setTestNow(now()->addMinute());
    $health->recordFailure('earnings:release', 'Ledger lock timeout.');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['views:aggregate', '16/11/2026 10:00', 'OK'])
        ->assertSeeInOrder(['earnings:release', '16/11/2026 10:01', 'Ledger lock timeout.', 'Failed'])
        ->assertSeeInOrder(['sepay:sync', 'Never', 'Late'])
        ->assertDontSee('Member statistics are not updating.');
});

it('warns prominently when member statistics are not being aggregated', function (): void {
    app(ScheduleHealth::class)->recordSuccess('views:aggregate');
    Carbon::setTestNow(now()->addMinutes(11));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Member statistics are not updating.');
});

it('escapes failure output', function (): void {
    app(ScheduleHealth::class)->recordFailure('fraud:scan', '<script>alert(1)</script>');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('keeps scheduler details away from members', function (): void {
    app(ScheduleHealth::class)->recordFailure('fraud:scan', 'Secret stack trace.');

    $this->actingAs(User::factory()->create())
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertDontSee('fraud:scan')
        ->assertDontSee('Secret stack trace.');
});
