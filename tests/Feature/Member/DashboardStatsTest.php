<?php

use App\Enums\ViewStatus;
use App\Models\DailyStat;
use App\Models\Link;
use App\Models\User;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now('UTC')->setDate(2026, 11, 13)->setTime(12, 0));
    $this->member = User::factory()->create();
});

it('returns month totals from daily stats without other members data', function () {
    DailyStat::factory()->create(['user_id' => $this->member->id, 'stat_date' => '2026-11-02', 'views' => 100, 'earn_micro' => 500_000, 'ref_earn_micro' => 60_000]);
    DailyStat::factory()->create(['user_id' => $this->member->id, 'stat_date' => '2026-11-05', 'views' => 300, 'earn_micro' => 1_500_000, 'ref_earn_micro' => 0]);
    DailyStat::factory()->create(['user_id' => $this->member->id, 'stat_date' => '2026-10-31', 'views' => 999, 'earn_micro' => 9_000_000]);
    DailyStat::factory()->create(['stat_date' => '2026-11-03', 'views' => 777, 'earn_micro' => 7_000_000]);

    $this->actingAs($this->member)
        ->postJson(route('member.dashboard.data'), ['date' => '20/11/2026'])
        ->assertStatus(422);

    $this->actingAs($this->member)
        ->postJson(route('member.dashboard.data'), ['date' => '13/11/2026'])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'total_view' => 400,
            'total_publisher_earn' => '$2.000',
            'total_ref_earn' => '$0.060',
            'total_income' => '$2.060',
            'average_cpm' => '$5.000',
        ])
        ->assertJsonCount(2, 'pay_view_data')
        ->assertJsonPath('pay_view_data.0', ['date' => '02/11/2026', 'value' => 100])
        ->assertJsonPath('publisher_earn_data.1.value', 1.5);
});

it('returns zeros and empty series for an empty month', function () {
    $this->actingAs($this->member)
        ->postJson(route('member.dashboard.data'), ['date' => '01/09/2026'])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'info' => '',
            'pay_view_data' => [],
            'publisher_earn_data' => [],
            'cpm_data' => [],
            'ref_data' => [],
            'total_view' => 0,
            'total_publisher_earn' => '$0.000',
            'average_cpm' => '$0.000',
        ]);
});

it('rejects a bad or missing date with 422 json', function (array $payload) {
    $this->actingAs($this->member)
        ->postJson(route('member.dashboard.data'), $payload)
        ->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonStructure(['info']);
})->with([
    'garbage' => [['date' => 'banana']],
    'future' => [['date' => '01/01/2999']],
    'missing' => [[]],
    'impossible day' => [['date' => '31/02/2026']],
]);

it('rejects guests', function () {
    $this->postJson(route('member.dashboard.data'), ['date' => '13/11/2026'])->assertUnauthorized();
    $this->get(route('member.statistic'))->assertRedirect();
    $this->get(route('member.popular-link'))->assertRedirect();
    $this->get(route('member.dashboard'))->assertRedirect();
});

it('rejects a bad month with 422 for ajax and falls back to the current month on html pages', function (string $month) {
    $this->actingAs($this->member)
        ->getJson(route('member.statistic', ['month' => $month]))
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    $this->actingAs($this->member)
        ->get(route('member.statistic', ['month' => $month]))
        ->assertOk()
        ->assertSee('data-date="13/11/2026"', false);

    $this->actingAs($this->member)
        ->get(route('member.popular-link', ['month' => $month]))
        ->assertOk()
        ->assertSee('<option value="11/2026" selected>', false);
})->with(['13/2026', 'abc', '12/2026']);

it('shows the requested month on the statistic page', function () {
    $this->actingAs($this->member)
        ->get(route('member.statistic', ['month' => '09/2026']))
        ->assertOk()
        ->assertSee('data-date="01/09/2026"', false)
        ->assertSee('Total views')
        ->assertSee('Income chart');
});

it('shows income boxes and notices on the overview', function () {
    $member = User::factory()->create(['balance_micro' => 50_000]);
    DailyStat::factory()->create(['user_id' => $member->id, 'stat_date' => '2026-11-13', 'earn_micro' => 100_000, 'ref_earn_micro' => 10_000]);
    DailyStat::factory()->create(['user_id' => $member->id, 'stat_date' => '2026-11-12', 'earn_micro' => 200_000, 'ref_earn_micro' => 0]);
    DailyStat::factory()->create(['user_id' => $member->id, 'stat_date' => '2026-11-01', 'earn_micro' => 300_000, 'ref_earn_micro' => 0]);
    DailyStat::factory()->create(['user_id' => $member->id, 'stat_date' => '2026-10-31', 'earn_micro' => 900_000, 'ref_earn_micro' => 0]);

    $this->actingAs($member)
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertSee('$ 0.110')
        ->assertSee('$ 0.200')
        ->assertSee('$ 0.610')
        ->assertSee('$ 0.050')
        ->assertSee('Notices')
        ->assertSee(route('member.statistic', ['month' => '11/2026']), false);
});

it('lists at most ten top links ordered by views then earnings', function () {
    $validViews = function (Link $link, int $count, int $earnEach, ViewStatus $status = ViewStatus::Released): void {
        View::factory()->count($count)->create([
            'link_id' => $link->id,
            'user_id' => $link->user_id,
            'status' => $status,
            'earn_micro' => $earnEach,
            'created_at' => now(),
        ]);
    };

    $links = Link::factory()->count(12)->create(['user_id' => $this->member->id]);

    foreach ($links as $index => $link) {
        $validViews($link, 1 + $index, 1_000);
    }

    $tieLow = Link::factory()->create(['user_id' => $this->member->id, 'url' => 'https://tie-low.test']);
    $tieHigh = Link::factory()->create(['user_id' => $this->member->id, 'url' => 'https://tie-high.test']);
    $validViews($tieLow, 12, 1);
    $validViews($tieHigh, 12, 5_000);
    $validViews($links[0], 50, 1, ViewStatus::Invalid);

    $stranger = Link::factory()->create(['url' => 'https://stranger.test']);
    $validViews($stranger, 40, 1_000);

    $response = $this->actingAs($this->member)->get(route('member.popular-link'))->assertOk();

    $response->assertDontSee('stranger.test');
    $response->assertSeeInOrder(['tie-high.test', $links[11]->url, 'tie-low.test', $links[10]->url]);
    expect(substr_count($response->getContent(), '<td>'.'1'.'</td>'))->toBe(1)
        ->and(preg_match_all('/<td class="text-right">/', $response->getContent()))->toBe(20);
});

it('shows earnings and a 30 day table on the link info page', function () {
    $link = Link::factory()->create(['user_id' => $this->member->id]);

    View::factory()->count(2)->create(['link_id' => $link->id, 'status' => ViewStatus::Released, 'earn_micro' => 50_000, 'created_at' => now()->subDay()]);
    View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Pending, 'earn_micro' => 50_000, 'created_at' => now()]);
    View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Invalid, 'earn_micro' => 0, 'created_at' => now()]);
    View::factory()->create(['link_id' => $link->id, 'status' => ViewStatus::Released, 'earn_micro' => 700_000, 'created_at' => now()->subDays(45)]);

    $this->actingAs($this->member)
        ->get(route('link.info', $link->alias))
        ->assertOk()
        ->assertSee('$ 0.850')
        ->assertSee('Last 30 days')
        ->assertSeeInOrder(['13/11/2026', '12/11/2026'])
        ->assertDontSee('0.700');
});
