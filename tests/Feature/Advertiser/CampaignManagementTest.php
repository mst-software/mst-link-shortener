<?php

use App\Enums\ReviewStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function advertiserCampaignPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Shoe store',
        'keyword' => 'best running shoes',
        'domain' => 'shoe-store.com',
        'daily_limit' => 100,
    ], $overrides);
}

beforeEach(function () {
    $this->advertiser = User::factory()->advertiser()->create();
});

it('creates a pending campaign owned by the advertiser', function () {
    Storage::fake('public');

    $this->actingAs($this->advertiser)
        ->post(route('advertiser.campaigns.store'), advertiserCampaignPayload([
            'image_google' => UploadedFile::fake()->image('google.png'),
        ]))
        ->assertRedirect();

    $campaign = Campaign::query()->sole();

    expect($campaign->user_id)->toBe($this->advertiser->id)
        ->and($campaign->review_status)->toBe(ReviewStatus::Pending)
        ->and($campaign->widget_key)->toHaveLength(24)
        ->and($campaign->display_domain)->toBe('shoe-st***.com')
        ->and($campaign->weight)->toBe(1)
        ->and($campaign->stage1_seconds)->toBe(60)
        ->and($campaign->stage2_seconds)->toBe(15)
        ->and($campaign->image_google)->not->toBeNull();
    Storage::disk('public')->assertExists($campaign->image_google);
});

it('validates the campaign form', function () {
    $this->actingAs($this->advertiser)
        ->post(route('advertiser.campaigns.store'), advertiserCampaignPayload(['domain' => 'co.uk', 'keyword' => '']))
        ->assertSessionHasErrors(['domain', 'keyword']);

    expect(Campaign::query()->count())->toBe(0);
});

it('cannot set admin only fields', function () {
    $this->actingAs($this->advertiser)
        ->post(route('advertiser.campaigns.store'), advertiserCampaignPayload([
            'weight' => 900,
            'price_micro' => 1,
            'review_status' => 'approved',
            'user_id' => 999,
        ]))
        ->assertRedirect();

    $campaign = Campaign::query()->sole();

    expect($campaign->weight)->toBe(1)
        ->and($campaign->price_micro)->toBeNull()
        ->and($campaign->review_status)->toBe(ReviewStatus::Pending)
        ->and($campaign->user_id)->toBe($this->advertiser->id);
});

it('lists only my campaigns', function () {
    Campaign::factory()->forAdvertiser($this->advertiser)->create(['name' => 'Mine one']);
    Campaign::factory()->forAdvertiser()->create(['name' => 'Someone else']);
    Campaign::factory()->create(['name' => 'Admin campaign']);

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.campaigns.index'))
        ->assertOk()
        ->assertSee('Mine one')
        ->assertDontSee('Someone else')
        ->assertDontSee('Admin campaign');
});

it('returns 404 for another advertiser campaign on edit, update and toggle', function () {
    $other = Campaign::factory()->forAdvertiser()->create();

    $this->actingAs($this->advertiser)->get(route('advertiser.campaigns.edit', $other))->assertNotFound();
    $this->actingAs($this->advertiser)->put(route('advertiser.campaigns.update', $other), advertiserCampaignPayload())->assertNotFound();
    $this->actingAs($this->advertiser)->patch(route('advertiser.campaigns.toggle', $other))->assertNotFound();
});

it('sends an approved campaign back to pending when the keyword or domain change', function (string $field, string $value) {
    $campaign = Campaign::factory()->forAdvertiser($this->advertiser)->create();

    $this->actingAs($this->advertiser)
        ->put(route('advertiser.campaigns.update', $campaign), advertiserCampaignPayload([
            'name' => $campaign->name,
            'keyword' => $campaign->keyword,
            'domain' => $campaign->domain,
            'daily_limit' => $campaign->daily_limit,
            $field => $value,
        ]))
        ->assertRedirect();

    expect($campaign->refresh()->review_status)->toBe(ReviewStatus::Pending);
})->with([
    'keyword' => ['keyword', 'a different keyword'],
    'domain' => ['domain', 'another-site.com'],
]);

it('keeps the approval when only the name or daily limit change', function () {
    $campaign = Campaign::factory()->forAdvertiser($this->advertiser)->create(['daily_limit' => 10]);

    $this->actingAs($this->advertiser)
        ->put(route('advertiser.campaigns.update', $campaign), advertiserCampaignPayload([
            'name' => 'Renamed',
            'keyword' => $campaign->keyword,
            'domain' => $campaign->domain,
            'daily_limit' => 500,
        ]))
        ->assertRedirect();

    $campaign->refresh();

    expect($campaign->review_status)->toBe(ReviewStatus::Approved)
        ->and($campaign->name)->toBe('Renamed')
        ->and($campaign->daily_limit)->toBe(500);
});

it('resubmits a rejected campaign as pending and clears the note', function () {
    $campaign = Campaign::factory()->forAdvertiser($this->advertiser)->create([
        'review_status' => ReviewStatus::Rejected,
        'review_note' => 'Gambling content.',
    ]);

    $this->actingAs($this->advertiser)
        ->put(route('advertiser.campaigns.update', $campaign), advertiserCampaignPayload([
            'keyword' => $campaign->keyword,
            'domain' => $campaign->domain,
        ]))
        ->assertRedirect();

    $campaign->refresh();

    expect($campaign->review_status)->toBe(ReviewStatus::Pending)
        ->and($campaign->review_note)->toBeNull();
});

it('lets the advertiser pause and resume only approved campaigns', function () {
    $approved = Campaign::factory()->forAdvertiser($this->advertiser)->create(['is_active' => true]);
    $pending = Campaign::factory()->forAdvertiser($this->advertiser)->pending()->create(['is_active' => true]);

    $this->actingAs($this->advertiser)->patch(route('advertiser.campaigns.toggle', $approved))->assertRedirect();
    expect($approved->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->advertiser)->patch(route('advertiser.campaigns.toggle', $approved))->assertRedirect();
    expect($approved->refresh()->is_active)->toBeTrue();

    $this->actingAs($this->advertiser)
        ->patch(route('advertiser.campaigns.toggle', $pending))
        ->assertRedirect()
        ->assertSessionHas('error');
    expect($pending->refresh()->is_active)->toBeTrue();
});

it('shows the embed snippet only when the campaign is approved', function () {
    $approved = Campaign::factory()->forAdvertiser($this->advertiser)->create();
    $pending = Campaign::factory()->forAdvertiser($this->advertiser)->pending()->create();

    $this->actingAs($this->advertiser)->get(route('advertiser.campaigns.edit', $approved))
        ->assertSee($approved->widget_key);
    $this->actingAs($this->advertiser)->get(route('advertiser.campaigns.edit', $pending))
        ->assertDontSee($pending->widget_key);
});

it('shows the rejection note to the owner', function () {
    $campaign = Campaign::factory()->forAdvertiser($this->advertiser)->create([
        'review_status' => ReviewStatus::Rejected,
        'review_note' => 'Please remove adult content.',
    ]);

    $this->actingAs($this->advertiser)->get(route('advertiser.campaigns.edit', $campaign))
        ->assertSee('Please remove adult content.');
});
