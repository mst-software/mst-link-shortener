<?php

use App\Enums\ViewStatus;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\FraudFlag;
use App\Models\User;
use App\Models\View;
use App\Models\VisitSession;
use App\Services\Settings;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * A valid payload for the campaign form, with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function campaignPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Houseplant guide',
        'keyword' => 'how to repot a houseplant',
        'domain' => 'example-advertiser.com',
        'display_domain' => '',
        'is_active' => '1',
        'weight' => 2,
        'daily_limit' => 0,
        'stage1_seconds' => 60,
        'stage2_seconds' => 15,
    ], $overrides);
}

/**
 * A valid settings payload, with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function visitSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'dwell_seconds' => 12,
        'unique_window_hours' => 24,
        'review_days' => 3,
        'min_withdraw_usd' => 5,
        'referral_percent' => 12,
        'session_minutes' => 30,
        'view_purge_days' => 60,
        'alias_length' => 6,
        'captcha_provider' => 'turnstile',
        'visit_mode' => 'auto',
        'code_session_seconds' => 600,
        'tutorial_video_url' => '',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('members cannot use the campaign pages', function () {
    $member = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $this->actingAs($member)->get(route('admin.campaigns.index'))->assertForbidden();
    $this->actingAs($member)->post(route('admin.campaigns.store'), campaignPayload())->assertForbidden();
    $this->actingAs($member)->delete(route('admin.campaigns.destroy', $campaign))->assertForbidden();
});

test('creating a campaign generates a unique widget key and a masked display domain', function () {
    $this->actingAs($this->admin)->post(route('admin.campaigns.store'), campaignPayload())->assertRedirect();
    $this->actingAs($this->admin)->post(route('admin.campaigns.store'), campaignPayload(['name' => 'Second']))->assertRedirect();

    $campaigns = Campaign::query()->orderBy('id')->get();

    expect($campaigns)->toHaveCount(2)
        ->and($campaigns[0]->widget_key)->toHaveLength(24)
        ->and($campaigns[0]->widget_key)->not->toBe($campaigns[1]->widget_key)
        ->and($campaigns[0]->display_domain)->toBe('example-adverti***.com');
});

test('the domain is reduced to a bare host', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaigns.store'), campaignPayload(['domain' => 'https://Example-Advertiser.com/some/page']))
        ->assertRedirect();

    expect(Campaign::query()->first()->domain)->toBe('example-advertiser.com');
});

test('instruction images are stored on the public disk', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.campaigns.store'), campaignPayload(['image_google' => UploadedFile::fake()->image('google.png')]))
        ->assertRedirect();

    $path = Campaign::query()->first()->image_google;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('images with the wrong format or size are rejected', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.campaigns.store'), campaignPayload([
            'image_google' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            'image_button' => UploadedFile::fake()->image('big.jpg')->size(2048),
        ]))
        ->assertSessionHasErrors(['image_google', 'image_button']);

    expect(Campaign::query()->count())->toBe(0);
});

test('admins can switch a campaign off and on', function () {
    $campaign = Campaign::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)->patch(route('admin.campaigns.toggle', $campaign))->assertRedirect(route('admin.campaigns.index'));
    expect($campaign->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->patch(route('admin.campaigns.toggle', $campaign));
    expect($campaign->fresh()->is_active)->toBeTrue();
});

test('updating a campaign keeps its widget key and shows the embed code', function () {
    $campaign = Campaign::factory()->create(['widget_key' => 'fixed-widget-key-000001']);

    $this->actingAs($this->admin)
        ->put(route('admin.campaigns.update', $campaign), campaignPayload(['name' => 'Renamed', 'widget_key' => 'changed']))
        ->assertRedirect(route('admin.campaigns.edit', $campaign));

    expect($campaign->fresh()->name)->toBe('Renamed')
        ->and($campaign->fresh()->widget_key)->toBe('fixed-widget-key-000001');

    $this->actingAs($this->admin)->get(route('admin.campaigns.edit', $campaign))
        ->assertOk()
        ->assertSee('data-campaign="fixed-widget-key-000001"', false);
});

test('the campaign list shows the campaigns', function () {
    Campaign::factory()->create(['name' => 'Visible campaign']);

    $this->actingAs($this->admin)->get(route('admin.campaigns.index'))
        ->assertOk()
        ->assertSee('Visible campaign');
});

test('admins can delete a campaign', function () {
    $campaign = Campaign::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.campaigns.destroy', $campaign))->assertRedirect(route('admin.campaigns.index'));

    expect(Campaign::query()->count())->toBe(0);
});

test('admins can store the visit mode, code session length and tutorial video', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), visitSettingsPayload([
            'visit_mode' => 'keyword',
            'code_session_seconds' => 900,
            'tutorial_video_url' => 'https://www.youtube.com/watch?v=abc123',
        ]))
        ->assertRedirect(route('admin.settings.edit'));

    expect(Settings::get('visit_mode'))->toBe('keyword')
        ->and(Settings::int('code_session_seconds'))->toBe(900)
        ->and(Settings::get('tutorial_video_url'))->toBe('https://www.youtube.com/watch?v=abc123');
});

test('invalid visit settings are rejected', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), visitSettingsPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'unknown mode' => [['visit_mode' => 'random'], 'visit_mode'],
    'session too short' => [['code_session_seconds' => 60], 'code_session_seconds'],
    'session too long' => [['code_session_seconds' => 4000], 'code_session_seconds'],
    'plain http video' => [['tutorial_video_url' => 'http://www.youtube.com/watch?v=abc'], 'tutorial_video_url'],
    'other video host' => [['tutorial_video_url' => 'https://example.com/video'], 'tutorial_video_url'],
]);

test('a public suffix cannot be used as the campaign domain', function (string $domain) {
    $this->actingAs($this->admin)
        ->post(route('admin.campaigns.store'), campaignPayload(['domain' => $domain]))
        ->assertSessionHasErrors('domain');

    expect(Campaign::query()->count())->toBe(0);
})->with(['co.uk', 'com.vn', 'CO.UK', 'https://com.vn/path']);

test('a site below a two-label suffix is still accepted', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaigns.store'), campaignPayload(['domain' => 'shop.co.uk']))
        ->assertSessionHasNoErrors();

    expect(Campaign::query()->first()->domain)->toBe('shop.co.uk');
});

test('localhost domains are refused outside a local environment', function (string $domain) {
    $this->actingAs($this->admin)
        ->post(route('admin.campaigns.store'), campaignPayload(['domain' => $domain]))
        ->assertSessionHasErrors('domain');
})->with(['localhost', '127.0.0.1']);

test('localhost domains are accepted in a local environment', function (string $domain) {
    $this->app->detectEnvironment(fn () => 'local');
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->actingAs($this->admin)
        ->post(route('admin.campaigns.store'), campaignPayload(['domain' => $domain]))
        ->assertSessionHasNoErrors();

    expect(Campaign::query()->first()->domain)->toBe($domain);
})->with(['localhost', '127.0.0.1']);

test('the campaign list counts issued codes, valid views and reports per campaign', function () {
    $busy = Campaign::factory()->create();
    $quiet = Campaign::factory()->create();

    CodeAttempt::factory()->issued()->count(2)->create(['campaign_id' => $busy->id]);
    CodeAttempt::factory()->issued()->create(['campaign_id' => $busy->id, 'issued_at' => now()->subDays(2)]);
    CodeAttempt::factory()->issued()->create(['campaign_id' => $quiet->id]);

    $session = VisitSession::factory()->create(['campaign_id' => $busy->id]);
    $other = VisitSession::factory()->create(['campaign_id' => $busy->id]);
    $cancelled = VisitSession::factory()->create(['campaign_id' => $busy->id]);
    View::factory()->create(['visit_session_id' => $session->id, 'status' => ViewStatus::Pending]);
    View::factory()->create(['visit_session_id' => $other->id, 'status' => ViewStatus::Released]);
    View::factory()->create(['visit_session_id' => $cancelled->id, 'status' => ViewStatus::Cancelled]);

    FraudFlag::factory()->count(2)->create(['reason' => 'visitor_report', 'details' => ['campaign_id' => $busy->id]]);
    FraudFlag::factory()->create(['reason' => 'suspicious_traffic', 'details' => ['campaign_id' => $busy->id]]);

    $stats = $this->actingAs($this->admin)->get(route('admin.campaigns.index'))->assertOk()->viewData('stats');

    expect($stats[$busy->id])->toBe(['issued_today' => 2, 'valid_views' => 2, 'reports' => 2])
        ->and($stats[$quiet->id])->toBe(['issued_today' => 1, 'valid_views' => 0, 'reports' => 0]);
});

test('the campaign list runs the same number of queries for any number of campaigns', function () {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('admin.campaigns.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    Campaign::factory()->create();
    $one = $countQueries();

    Campaign::factory()->count(5)->create();

    expect($countQueries())->toBe($one);
});

test('a Vimeo tutorial video link is accepted and the visitor script can embed it', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), visitSettingsPayload(['tutorial_video_url' => 'https://vimeo.com/123456789']))
        ->assertSessionHasNoErrors();

    expect(Settings::get('tutorial_video_url'))->toBe('https://vimeo.com/123456789')
        ->and(file_get_contents(resource_path('js/visit.js')))->toContain('https://player.vimeo.com/video/');
});
