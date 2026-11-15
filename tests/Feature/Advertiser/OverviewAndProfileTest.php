<?php

use App\Enums\ReviewStatus;
use App\Enums\ViewStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->advertiser = User::factory()->advertiser()->create(['balance_micro' => 5_000_000]);
});

it('shows overview totals for today and seven days', function () {
    $campaign = Campaign::factory()->forAdvertiser($this->advertiser)->create();

    foreach ([now(), now(), now()->subDays(3), now()->subDays(10)] as $when) {
        View::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ViewStatus::Pending,
            'advertiser_cost_micro' => 10_000,
            'created_at' => $when,
        ]);
    }

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.dashboard'))
        ->assertOk()
        ->assertSee('$5.00')
        ->assertSeeInOrder(['Spent today', '$0.02', 'Spent last 7 days', '$0.03']);
});

it('counts campaigns by review status', function () {
    Campaign::factory()->forAdvertiser($this->advertiser)->count(2)->create();
    Campaign::factory()->forAdvertiser($this->advertiser)->pending()->create();
    Campaign::factory()->forAdvertiser($this->advertiser)->create(['review_status' => ReviewStatus::Rejected]);
    Campaign::factory()->forAdvertiser()->count(5)->create();

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.dashboard'))
        ->assertSeeInOrder(['Pending review', '1', 'Approved', '2', 'Rejected', '1']);
});

it('warns when the balance is low', function () {
    $this->advertiser->forceFill(['balance_micro' => 50_000])->save();

    $this->actingAs($this->advertiser)
        ->get(route('advertiser.dashboard'))
        ->assertSee('Your balance is running low');
});

it('does not warn when the balance is fine', function () {
    $this->actingAs($this->advertiser)
        ->get(route('advertiser.dashboard'))
        ->assertDontSee('Your balance is running low');
});

it('changes the advertiser password', function () {
    $this->actingAs($this->advertiser)
        ->post(route('advertiser.profile.password'), ['current_password' => 'password', 'password' => 'brand-new-pass'])
        ->assertRedirect(route('advertiser.profile'));

    expect(Hash::check('brand-new-pass', $this->advertiser->refresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $this->actingAs($this->advertiser)
        ->post(route('advertiser.profile.password'), ['current_password' => 'wrong', 'password' => 'brand-new-pass'])
        ->assertSessionHasErrors('current_password');
});

it('changes the advertiser email', function () {
    $this->actingAs($this->advertiser)
        ->post(route('advertiser.profile.email'), ['email' => 'new@example.com', 'current_password' => 'password'])
        ->assertRedirect(route('advertiser.profile'));

    expect($this->advertiser->refresh()->email)->toBe('new@example.com');
});

it('shows the profile page', function () {
    $this->actingAs($this->advertiser)
        ->get(route('advertiser.profile'))
        ->assertOk()
        ->assertSee($this->advertiser->email);
});
