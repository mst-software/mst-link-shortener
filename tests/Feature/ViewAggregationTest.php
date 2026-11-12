<?php

use App\Enums\ViewStatus;
use App\Models\DailyStat;
use App\Models\Link;
use App\Models\User;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeView(User $user, array $attributes = []): View
{
    $link = Link::factory()->create(['user_id' => $user->id]);

    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $view = View::factory()->create(array_merge(['link_id' => $link->id, 'user_id' => $user->id], $attributes));

    if ($createdAt !== null) {
        // created_at is not mass assignable, so set it explicitly.
        $view->forceFill(['created_at' => $createdAt])->save();
    }

    return $view;
}

it('folds valid views into daily stats per user and UTC day', function () {
    $user = User::factory()->create();
    $day = now()->utc()->startOfDay()->addHours(5);

    makeView($user, ['earn_micro' => 1_000, 'created_at' => $day]);
    makeView($user, ['earn_micro' => 2_000, 'created_at' => $day, 'status' => ViewStatus::Released]);
    makeView($user, ['earn_micro' => 4_000, 'created_at' => $day->copy()->subDay()]);

    $this->artisan('views:aggregate')->assertSuccessful();

    $today = DailyStat::query()->where('user_id', $user->id)->whereDate('stat_date', $day->toDateString())->firstOrFail();
    $yesterday = DailyStat::query()->where('user_id', $user->id)->whereDate('stat_date', $day->copy()->subDay()->toDateString())->firstOrFail();

    expect($today->views)->toBe(2)
        ->and($today->earn_micro)->toBe(3_000)
        ->and($yesterday->views)->toBe(1)
        ->and($yesterday->earn_micro)->toBe(4_000)
        ->and(View::query()->whereNull('aggregated_at')->count())->toBe(0);
});

it('gives the same result when aggregation runs twice', function () {
    $user = User::factory()->create();
    makeView($user, ['earn_micro' => 1_500]);
    makeView($user, ['earn_micro' => 2_500]);

    $this->artisan('views:aggregate')->assertSuccessful();
    $this->artisan('views:aggregate')->assertSuccessful();

    $stat = DailyStat::query()->where('user_id', $user->id)->sole();

    expect($stat->views)->toBe(2)
        ->and($stat->earn_micro)->toBe(4_000);
});

it('picks up only new views on a later run', function () {
    $user = User::factory()->create();
    makeView($user, ['earn_micro' => 1_000]);
    $this->artisan('views:aggregate')->assertSuccessful();

    makeView($user, ['earn_micro' => 500]);
    $this->artisan('views:aggregate')->assertSuccessful();

    $stat = DailyStat::query()->where('user_id', $user->id)->sole();

    expect($stat->views)->toBe(2)
        ->and($stat->earn_micro)->toBe(1_500);
});

it('does not aggregate invalid or cancelled views', function () {
    $user = User::factory()->create();
    makeView($user, ['status' => ViewStatus::Invalid]);
    makeView($user, ['status' => ViewStatus::Cancelled, 'earn_micro' => 9_000]);

    $this->artisan('views:aggregate')->assertSuccessful();

    expect(DailyStat::query()->count())->toBe(0);
});

it('purges old aggregated views but keeps recent and pending ones', function () {
    $user = User::factory()->create();
    $old = now()->subDays(61);

    $oldReleased = makeView($user, ['status' => ViewStatus::Released, 'created_at' => $old, 'aggregated_at' => now()]);
    $oldInvalid = makeView($user, ['status' => ViewStatus::Invalid, 'created_at' => $old, 'aggregated_at' => now()]);
    $oldPending = makeView($user, ['status' => ViewStatus::Pending, 'created_at' => $old, 'aggregated_at' => now()]);
    $oldNotAggregated = makeView($user, ['status' => ViewStatus::Released, 'created_at' => $old, 'aggregated_at' => null]);
    $recent = makeView($user, ['status' => ViewStatus::Released, 'created_at' => now()->subDays(5), 'aggregated_at' => now()]);

    $this->artisan('views:purge')->assertSuccessful();

    expect(View::query()->whereKey($oldReleased->id)->exists())->toBeFalse()
        ->and(View::query()->whereKey($oldInvalid->id)->exists())->toBeFalse()
        ->and(View::query()->whereKey($oldPending->id)->exists())->toBeTrue()
        ->and(View::query()->whereKey($oldNotAggregated->id)->exists())->toBeTrue()
        ->and(View::query()->whereKey($recent->id)->exists())->toBeTrue();
});
