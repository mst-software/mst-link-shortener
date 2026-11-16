<?php

use App\Enums\ViewStatus;
use App\Models\Link;
use App\Models\User;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now('UTC')->setDate(2026, 11, 13)->setTime(12, 0));
    $this->member = User::factory()->create();

    $this->views = function (Link $link, int $count, array $attributes = []): void {
        View::factory()->count($count)->create([
            'link_id' => $link->id,
            'user_id' => $link->user_id,
            'status' => ViewStatus::Released,
            'earn_micro' => 1_000,
            'created_at' => now(),
            ...$attributes,
        ]);
    };
});

it('sums valid views and earnings per country, link and level for the member only', function () {
    $first = Link::factory()->create(['user_id' => $this->member->id, 'url' => 'https://first.test']);
    $second = Link::factory()->create(['user_id' => $this->member->id, 'url' => 'https://second.test']);

    ($this->views)($first, 3, ['country' => 'US', 'level' => 1, 'earn_micro' => 2_000]);
    ($this->views)($first, 1, ['country' => 'VN', 'level' => 2, 'earn_micro' => 500, 'status' => ViewStatus::Pending]);
    ($this->views)($second, 2, ['country' => 'US', 'level' => 2, 'earn_micro' => 1_500]);
    ($this->views)($second, 4, ['country' => 'XX', 'level' => 3, 'earn_micro' => 0, 'created_at' => now()->subDays(20)]);

    ($this->views)($first, 5, ['country' => 'US', 'status' => ViewStatus::Invalid, 'earn_micro' => 9_000]);
    ($this->views)($first, 5, ['country' => 'US', 'status' => ViewStatus::Cancelled, 'earn_micro' => 9_000]);
    ($this->views)($first, 5, ['country' => 'US', 'created_at' => now()->subDays(31)]);
    ($this->views)(Link::factory()->create(['url' => 'https://stranger.test']), 7, ['country' => 'US']);

    $this->actingAs($this->member)
        ->get(route('member.statistic'))
        ->assertOk()
        ->assertViewHas('range', 30)
        ->assertViewHas('breakdowns', function (array $breakdowns) use ($first, $second): bool {
            expect($breakdowns['by_country'])->toBe([
                ['country' => 'US', 'name' => 'United States', 'views' => 5, 'earn_micro' => 9_000],
                ['country' => 'XX', 'name' => 'Unknown', 'views' => 4, 'earn_micro' => 0],
                ['country' => 'VN', 'name' => 'Vietnam', 'views' => 1, 'earn_micro' => 500],
            ]);
            expect($breakdowns['by_level'])->toBe([
                ['level' => 1, 'views' => 3, 'earn_micro' => 6_000],
                ['level' => 2, 'views' => 3, 'earn_micro' => 3_500],
                ['level' => 3, 'views' => 4, 'earn_micro' => 0],
            ]);
            expect($breakdowns['by_link']->map(fn (array $row): array => [$row['link']->id, $row['views'], $row['earn_micro']])->all())
                ->toBe([[$second->id, 6, 3_000], [$first->id, 4, 6_500]]);

            return true;
        })
        ->assertSee('United States')
        ->assertSee('Level 3')
        ->assertDontSee('https://stranger.test');
});

it('limits the breakdowns to the selected range', function () {
    $link = Link::factory()->create(['user_id' => $this->member->id]);
    ($this->views)($link, 2, ['created_at' => now()->subDays(6)->startOfDay()]);
    ($this->views)($link, 3, ['created_at' => now()->subDays(7)]);
    ($this->views)($link, 4, ['created_at' => now()->subDays(80)]);

    $totalViews = fn (string $range): int => collect(
        $this->actingAs($this->member)->get(route('member.statistic', ['range' => $range]))->viewData('breakdowns')['by_level'],
    )->sum('views');

    expect($totalViews('7'))->toBe(2)
        ->and($totalViews('30'))->toBe(5)
        ->and($totalViews('90'))->toBe(9);
});

it('falls back to 30 days for an invalid range on the page and rejects it with 422 for ajax', function (string $range) {
    $this->actingAs($this->member)
        ->get(route('member.statistic', ['range' => $range]))
        ->assertOk()
        ->assertViewHas('range', 30)
        ->assertSee('<option value="30" selected>Last 30 days</option>', false);

    $this->actingAs($this->member)
        ->getJson(route('member.statistic', ['range' => $range]))
        ->assertStatus(422)
        ->assertJson(['success' => false]);
})->with(['14', 'abc', '-7', '7.5']);

it('keeps the range when changing the month and the month when changing the range', function () {
    $this->actingAs($this->member)
        ->get(route('member.statistic', ['month' => '10/2026', 'range' => '7']))
        ->assertOk()
        ->assertSeeInOrder([
            '<input type="hidden" name="range" value="7">',
            '<select name="month"',
            '<input type="hidden" name="month" value="10/2026">',
            '<select name="range"',
        ], false);
});

it('labels the breakdowns with the rolling day range rather than the selected month', function () {
    $this->actingAs($this->member)
        ->get(route('member.statistic', ['month' => '09/2026', 'range' => '7']))
        ->assertOk()
        ->assertSee('View breakdowns: last 7 days (UTC)')
        ->assertSee('regardless of the month selected above');
});

it('shows empty states and the retention notice when there are no views', function () {
    $this->actingAs($this->member)
        ->get(route('member.statistic', ['range' => '90']))
        ->assertOk()
        ->assertSee('No valid views in this period.')
        ->assertSee('only kept for the last 60 days')
        ->assertSee('Days before 14/09/2026 are not included.');
});

it('does not warn about missing days when the range is within retention', function () {
    $this->actingAs($this->member)
        ->get(route('member.statistic', ['range' => '7']))
        ->assertOk()
        ->assertSee('only kept for the last 60 days')
        ->assertDontSee('are not included.');
});
