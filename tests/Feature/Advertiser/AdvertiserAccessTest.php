<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers an advertiser with accepted terms', function () {
    $response = $this->post(route('advertiser.register'), [
        'username' => 'acme_ads',
        'email' => 'ads@example.com',
        'password' => 'secret-pass-1',
        'accept' => '1',
    ]);

    $user = User::query()->where('username', 'acme_ads')->firstOrFail();

    $response->assertRedirect(route('advertiser.dashboard'));
    expect($user->role)->toBe(UserRole::Advertiser)
        ->and($user->terms_accepted_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('rejects registration without accepting the terms', function () {
    $this->post(route('advertiser.register'), [
        'username' => 'acme_ads',
        'email' => 'ads@example.com',
        'password' => 'secret-pass-1',
    ])->assertSessionHasErrors('accept');

    expect(User::query()->where('username', 'acme_ads')->exists())->toBeFalse();
});

it('shows the advertiser sign up page', function () {
    $this->get(route('advertiser.register'))
        ->assertOk()
        ->assertSee('Advertiser');
});

it('redirects each role to its own dashboard after login', function (string $state, string $route) {
    $user = User::factory()->{$state}()->create(['username' => 'role_user']);

    $this->post(route('login'), ['username' => 'role_user', 'password' => 'password'])
        ->assertRedirect(route($route));
})->with([
    'advertiser' => ['advertiser', 'advertiser.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);

it('redirects a member to the member dashboard after login', function () {
    User::factory()->create(['username' => 'plain_member']);

    $this->post(route('login'), ['username' => 'plain_member', 'password' => 'password'])
        ->assertRedirect(route('member.dashboard'));
});

it('shows the advertiser dashboard to an advertiser', function () {
    $this->actingAs(User::factory()->advertiser()->create())
        ->get(route('advertiser.dashboard'))
        ->assertOk();
});

it('blocks an advertiser from member routes', function () {
    $this->actingAs(User::factory()->advertiser()->create())
        ->get(route('member.dashboard'))
        ->assertForbidden();
});

it('blocks a member from advertiser routes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('advertiser.dashboard'))
        ->assertForbidden();
});

it('blocks an advertiser from link shortening', function () {
    $this->actingAs(User::factory()->advertiser()->create())
        ->post(route('links.shorten'), ['url' => 'https://example.com'])
        ->assertForbidden();
});
