<?php

use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Services\Visits\CodeIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function widgetCampaign(array $attributes = []): Campaign
{
    return Campaign::factory()->create(array_merge([
        'domain' => 'advertiser.test',
        'widget_key' => 'widget-key-for-tests-01',
        'stage1_seconds' => 60,
        'stage2_seconds' => 15,
        'daily_limit' => 0,
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $data
 */
function widgetPost(string $path, array $data, string $origin = 'https://advertiser.test'): TestResponse
{
    return test()->postJson('/widget/'.$path, $data, [
        'Origin' => $origin,
        'User-Agent' => 'Mozilla/5.0 WidgetTest',
    ]);
}

function widgetStart(Campaign $campaign, string $pageLoadId = 'page-load-one'): string
{
    return widgetPost('start', [
        'campaign' => $campaign->widget_key,
        'referrer' => 'https://www.google.com/',
        'page_load_id' => $pageLoadId,
        'fingerprint' => 'abcdef0123456789',
    ])->assertOk()->json('attempt_token');
}

it('starts an attempt and returns the stage and timings', function (): void {
    $campaign = widgetCampaign();

    $response = widgetPost('start', [
        'campaign' => $campaign->widget_key,
        'referrer' => 'https://www.google.com/search?q=x',
        'page_load_id' => 'page-load-one',
        'fingerprint' => 'abcdef0123456789',
    ]);

    $response->assertOk()
        ->assertJsonPath('stage', 1)
        ->assertJsonPath('stage1_seconds', 60)
        ->assertJsonPath('stage2_seconds', 15)
        ->assertJsonPath('remaining_seconds', 60)
        ->assertHeader('Access-Control-Allow-Origin', 'https://advertiser.test');

    $attempt = CodeAttempt::query()->sole();
    expect($attempt->referrer_host)->toBe('www.google.com')
        ->and($attempt->ua_hash)->toBe(hash('sha256', 'Mozilla/5.0 WidgetTest'))
        ->and($attempt->code)->toBeNull();
});

it('rejects an unknown origin and never issues a code', function (): void {
    $campaign = widgetCampaign();

    widgetPost('start', [
        'campaign' => $campaign->widget_key,
        'page_load_id' => 'page-load-one',
    ], 'https://evil.example')
        ->assertForbidden()
        ->assertJsonPath('error', 'bad_origin')
        ->assertHeaderMissing('Access-Control-Allow-Origin');

    widgetPost('start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-one'], 'https://notadvertiser.test')
        ->assertForbidden();

    expect(CodeAttempt::query()->count())->toBe(0);
});

it('accepts subdomains of the campaign domain', function (): void {
    $campaign = widgetCampaign();

    widgetPost('start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-one'], 'https://blog.advertiser.test')
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://blog.advertiser.test');
});

it('rejects a request without an origin header', function (): void {
    $campaign = widgetCampaign();

    $this->postJson('/widget/start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-one'])
        ->assertForbidden()
        ->assertJsonPath('error', 'bad_origin');
});

it('reports a disabled campaign as unavailable at every endpoint', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    $campaign->update(['is_active' => false]);

    widgetPost('start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-two'])
        ->assertForbidden()
        ->assertJsonPath('error', 'campaign_unavailable');
    widgetPost('code', ['attempt_token' => $token])
        ->assertForbidden()
        ->assertJsonPath('error', 'campaign_unavailable');
});

it('reports an unknown campaign key as unavailable', function (): void {
    widgetPost('start', ['campaign' => 'no-such-key', 'page_load_id' => 'page-load-one'])
        ->assertNotFound()
        ->assertJsonPath('error', 'campaign_unavailable');
});

it('reports campaign_unavailable once the daily limit is reached', function (): void {
    $campaign = widgetCampaign(['daily_limit' => 1]);
    CodeAttempt::factory()->issued()->create(['campaign_id' => $campaign->id]);

    widgetPost('start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-one'])
        ->assertForbidden()
        ->assertJsonPath('error', 'campaign_unavailable');
});

it('rejects a forged or tampered attempt token', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    widgetPost('code', ['attempt_token' => $token.'x'])->assertStatus(400)->assertJsonPath('error', 'bad_token');
    widgetPost('code', ['attempt_token' => 'nonsense'])->assertStatus(400)->assertJsonPath('error', 'bad_token');
});

it('refuses a token used from a different user agent', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    $this->postJson('/widget/progress', ['attempt_token' => $token, 'page_load_id' => 'page-load-two'], [
        'Origin' => 'https://advertiser.test',
        'User-Agent' => 'Some Other Browser',
    ])->assertStatus(400)->assertJsonPath('error', 'bad_token');
});

it('answers too_early with the remaining seconds from the server clock', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    $this->travel(20)->seconds();

    widgetPost('progress', ['attempt_token' => $token, 'page_load_id' => 'page-load-two'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'too_early')
        ->assertJsonPath('remaining_seconds', 40);

    widgetPost('code', ['attempt_token' => $token])
        ->assertStatus(422)
        ->assertJsonPath('error', 'too_early');

    expect(CodeAttempt::query()->sole()->code)->toBeNull();
});

it('ignores any clock the client claims to have', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    widgetPost('progress', [
        'attempt_token' => $token,
        'page_load_id' => 'page-load-two',
        'elapsed' => 9999,
        'now' => now()->addHour()->timestamp,
    ])->assertStatus(422)->assertJsonPath('error', 'too_early');
});

it('requires a different page load before progressing', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign, 'page-load-one');

    $this->travel(61)->seconds();

    widgetPost('progress', ['attempt_token' => $token, 'page_load_id' => 'page-load-one'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'too_early');

    widgetPost('progress', ['attempt_token' => $token, 'page_load_id' => 'page-load-two'])
        ->assertOk()
        ->assertJsonPath('stage', 2)
        ->assertJsonPath('remaining_seconds', 15);
});

it('refuses the code before the second stage has elapsed', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    $this->travel(61)->seconds();
    widgetPost('progress', ['attempt_token' => $token, 'page_load_id' => 'page-load-two'])->assertOk();

    $this->travel(10)->seconds();

    widgetPost('code', ['attempt_token' => $token])
        ->assertStatus(422)
        ->assertJsonPath('error', 'too_early')
        ->assertJsonPath('remaining_seconds', 5);
});

it('issues the same code again when asked twice', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    $this->travel(61)->seconds();
    widgetPost('progress', ['attempt_token' => $token, 'page_load_id' => 'page-load-two'])->assertOk();
    $this->travel(16)->seconds();

    $first = widgetPost('code', ['attempt_token' => $token])->assertOk()->json('code');
    $second = widgetPost('code', ['attempt_token' => $token])->assertOk()->json('code');

    expect($first)->toBe($second)
        ->and($first)->toMatch('/\A['.CodeIssuer::ALPHABET.']{6}\z/')
        ->and(CodeAttempt::query()->whereNotNull('code')->count())->toBe(1);

    $attempt = CodeAttempt::query()->sole();
    expect($attempt->expires_at->isFuture())->toBeTrue()
        ->and($attempt->issued_at)->not->toBeNull();

    widgetPost('start', [
        'campaign' => $campaign->widget_key,
        'page_load_id' => 'page-load-three',
        'attempt_token' => $token,
    ])->assertOk()->assertJsonPath('stage', 3)->assertJsonPath('code', $first);
});

it('continues the same attempt when start is called again with the token', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    $this->travel(30)->seconds();

    widgetPost('start', [
        'campaign' => $campaign->widget_key,
        'page_load_id' => 'page-load-two',
        'attempt_token' => $token,
    ])->assertOk()
        ->assertJsonPath('attempt_token', $token)
        ->assertJsonPath('stage', 1)
        ->assertJsonPath('remaining_seconds', 30);

    expect(CodeAttempt::query()->count())->toBe(1);
});

it('answers a preflight request only for a valid origin', function (): void {
    $campaign = widgetCampaign();

    $this->call('OPTIONS', '/widget/start', ['campaign' => $campaign->widget_key], [], [], [
        'HTTP_ORIGIN' => 'https://advertiser.test',
    ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://advertiser.test');

    $this->call('OPTIONS', '/widget/start', ['campaign' => $campaign->widget_key], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example',
    ])->assertNoContent()->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('accepts a text/plain json body so the widget needs no preflight', function (): void {
    $campaign = widgetCampaign();

    $this->call('POST', '/widget/start', [], [], [], [
        'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        'HTTP_ORIGIN' => 'https://advertiser.test',
    ], json_encode(['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-one']))
        ->assertOk()
        ->assertJsonPath('stage', 1);
});

it('starts a fresh attempt instead of showing a code that was already used', function (): void {
    $campaign = widgetCampaign();
    $token = widgetStart($campaign);

    $this->travel(61)->seconds();
    widgetPost('progress', ['attempt_token' => $token, 'page_load_id' => 'page-load-two'])->assertOk();
    $this->travel(16)->seconds();
    $usedCode = widgetPost('code', ['attempt_token' => $token])->assertOk()->json('code');

    CodeAttempt::query()->update(['used_at' => now()]);

    $response = widgetPost('start', [
        'campaign' => $campaign->widget_key,
        'page_load_id' => 'page-load-three',
        'attempt_token' => $token,
    ])->assertOk()->assertJsonPath('stage', 1);

    expect($response->json('attempt_token'))->not->toBe($token)
        ->and($response->json())->not->toHaveKey('code')
        ->and(CodeAttempt::query()->where('code', $usedCode)->count())->toBe(1)
        ->and(CodeAttempt::query()->count())->toBe(2);
});

it('re-checks the daily limit when the code is issued and never overshoots it', function (): void {
    $campaign = widgetCampaign(['daily_limit' => 1]);
    $token = widgetStart($campaign);

    $this->travel(61)->seconds();
    widgetPost('progress', ['attempt_token' => $token, 'page_load_id' => 'page-load-two'])->assertOk();
    $this->travel(16)->seconds();

    // Another visitor takes the last code of the day while this attempt waits.
    CodeAttempt::factory()->issued()->create(['campaign_id' => $campaign->id]);

    widgetPost('code', ['attempt_token' => $token])
        ->assertForbidden()
        ->assertJsonPath('error', 'campaign_unavailable');

    expect(CodeAttempt::query()->whereNotNull('code')->count())->toBe(1)
        ->and(CodeAttempt::query()->whereKey(CodeAttempt::query()->whereNull('code')->value('id'))->exists())->toBeTrue();
});

it('continues the open attempt of the same browser instead of adding rows', function (): void {
    $campaign = widgetCampaign();
    $first = widgetStart($campaign, 'page-load-one');

    $second = widgetPost('start', [
        'campaign' => $campaign->widget_key,
        'page_load_id' => 'page-load-two',
    ])->assertOk()->json('attempt_token');

    expect($second)->toBe($first)
        ->and(CodeAttempt::query()->count())->toBe(1);
});

it('does not reuse an attempt of another browser or IP', function (): void {
    $campaign = widgetCampaign();
    widgetStart($campaign);

    $this->postJson('/widget/start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-x2'], [
        'Origin' => 'https://advertiser.test',
        'User-Agent' => 'Mozilla/5.0 AnotherBrowser',
    ])->assertOk();

    $this->postJson('/widget/start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-x3'], [
        'Origin' => 'https://advertiser.test',
        'User-Agent' => 'Mozilla/5.0 WidgetTest',
    ], )->assertOk();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->postJson('/widget/start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-x4'], [
            'Origin' => 'https://advertiser.test',
            'User-Agent' => 'Mozilla/5.0 WidgetTest',
        ])->assertOk();

    expect(CodeAttempt::query()->count())->toBe(3);
});

it('caps the attempts of one IP per campaign per UTC day', function (): void {
    $campaign = widgetCampaign();

    foreach (range(1, 5) as $i) {
        $this->postJson('/widget/start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-'.$i], [
            'Origin' => 'https://advertiser.test',
            'User-Agent' => 'Mozilla/5.0 Browser'.$i,
        ])->assertOk();
    }

    $this->postJson('/widget/start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-x6'], [
        'Origin' => 'https://advertiser.test',
        'User-Agent' => 'Mozilla/5.0 Browser6',
    ])->assertStatus(429)->assertJsonPath('error', 'too_many_attempts');

    expect(CodeAttempt::query()->count())->toBe(5);

    $this->travelTo(now('UTC')->addDay()->startOfDay()->addMinute());

    $this->postJson('/widget/start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-x7'], [
        'Origin' => 'https://advertiser.test',
        'User-Agent' => 'Mozilla/5.0 Browser6',
    ])->assertOk();
});

it('throttles widget start to twenty requests a minute per IP', function (): void {
    $campaign = widgetCampaign();

    foreach (range(1, 20) as $i) {
        widgetPost('start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'page-load-'.$i])->assertOk();
    }

    widgetPost('start', ['campaign' => $campaign->widget_key, 'page_load_id' => 'p21'])->assertStatus(429);

    // The other endpoints keep their own limit.
    widgetPost('progress', ['attempt_token' => 'x'])->assertStatus(400);
});
