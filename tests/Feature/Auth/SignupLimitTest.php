<?php

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\UserLogin;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
});

/**
 * @return array<string, string>
 */
function signupPayload(string $username): array
{
    return ['username' => $username, 'email' => $username.'@example.com', 'password' => 'Secret123!', 'accept' => '1'];
}

test('sign-ups from one ip are refused once the daily limit is reached', function () {
    Settings::set('signups_per_ip_per_day', 2);
    UserLogin::factory()->signup()->count(2)->create(['ip' => '203.0.113.9', 'created_at' => now()->subHours(3)]);

    $this->post(route('register'), signupPayload('third'))
        ->assertSessionHasErrors(['username' => RegisterRequest::SIGNUP_LIMIT_MESSAGE]);

    $this->post(route('advertiser.register'), signupPayload('brand'))
        ->assertSessionHasErrors(['username' => RegisterRequest::SIGNUP_LIMIT_MESSAGE]);

    expect(User::query()->whereIn('username', ['third', 'brand'])->exists())->toBeFalse();
    $this->assertGuest();
});

test('sign-ups older than 24 hours and from other ips do not count', function () {
    Settings::set('signups_per_ip_per_day', 2);
    UserLogin::factory()->signup()->count(2)->create(['ip' => '203.0.113.9', 'created_at' => now()->subHours(25)]);
    UserLogin::factory()->signup()->count(2)->create(['ip' => '203.0.113.10']);
    UserLogin::factory()->count(5)->create(['ip' => '203.0.113.9']);

    $this->post(route('register'), signupPayload('fresh'))->assertSessionHasNoErrors();

    expect(User::query()->where('username', 'fresh')->exists())->toBeTrue();
});

test('a limit of zero turns the sign-up limit off', function () {
    Settings::set('signups_per_ip_per_day', 0);
    UserLogin::factory()->signup()->count(10)->create(['ip' => '203.0.113.9']);

    $this->post(route('register'), signupPayload('unlimited'))->assertSessionHasNoErrors();
});

test('sign-ins are never blocked by the sign-up limit', function () {
    Settings::set('signups_per_ip_per_day', 1);
    UserLogin::factory()->signup()->count(3)->create(['ip' => '203.0.113.9']);
    $user = User::factory()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password'])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});
