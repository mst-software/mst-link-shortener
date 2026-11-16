<?php

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * @return array<string, string>
 */
function signupPayload(string $username): array
{
    return ['username' => $username, 'email' => $username.'@example.com', 'password' => 'Secret123!', 'accept' => '1'];
}

/**
 * Sign up from the given address as a fresh guest.
 */
function signUpFrom(string $ip, string $username, string $route = 'register'): TestResponse
{
    Auth::guard('web')->logout();

    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->post(route($route), signupPayload($username));
}

test('the default limit is ten sign-ups per network per day', function () {
    expect(Settings::int('signups_per_ip_per_day'))->toBe(10);
});

test('sign-ups from one ip are refused once the daily limit is reached, for members and advertisers alike', function () {
    Settings::set('signups_per_ip_per_day', 2);

    signUpFrom('203.0.113.9', 'first')->assertSessionHasNoErrors();
    signUpFrom('203.0.113.9', 'second', 'advertiser.register')->assertSessionHasNoErrors();
    signUpFrom('203.0.113.9', 'third')->assertSessionHasErrors(['username' => RegisterRequest::SIGNUP_LIMIT_MESSAGE]);
    signUpFrom('203.0.113.9', 'brand', 'advertiser.register')->assertSessionHasErrors(['username' => RegisterRequest::SIGNUP_LIMIT_MESSAGE]);

    expect(User::query()->whereIn('username', ['third', 'brand'])->exists())->toBeFalse();
});

test('another ipv4 address has its own count and the count resets after 24 hours', function () {
    Settings::set('signups_per_ip_per_day', 1);

    signUpFrom('203.0.113.9', 'first')->assertSessionHasNoErrors();
    signUpFrom('203.0.113.10', 'other')->assertSessionHasNoErrors();
    signUpFrom('203.0.113.9', 'refused')->assertSessionHasErrors('username');

    $this->travel(RegisterRequest::SIGNUP_WINDOW_SECONDS + 1)->seconds();

    signUpFrom('203.0.113.9', 'later')->assertSessionHasNoErrors();
});

test('ipv6 addresses are counted per /64 network', function () {
    Settings::set('signups_per_ip_per_day', 1);

    signUpFrom('2001:db8:1:2::1', 'first')->assertSessionHasNoErrors();
    signUpFrom('2001:db8:1:2:ffff::9', 'samenet')->assertSessionHasErrors('username');
    signUpFrom('2001:db8:1:3::1', 'othernet')->assertSessionHasNoErrors();
});

test('the signup network keeps ipv4 addresses and cuts ipv6 addresses to their /64', function (string $ip, string $network) {
    expect(RegisterRequest::signupNetwork($ip))->toBe($network);
})->with([
    ['203.0.113.9', '203.0.113.9'],
    ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::/64'],
    ['2001:DB8:1:2::abcd', '2001:db8:1:2::/64'],
]);

test('a limit of zero turns the sign-up limit off', function () {
    Settings::set('signups_per_ip_per_day', 0);

    foreach (range(1, 3) as $index) {
        signUpFrom('203.0.113.9', 'member'.$index)->assertSessionHasNoErrors();
    }
});

test('sign-ins are never blocked by the sign-up limit', function () {
    Settings::set('signups_per_ip_per_day', 1);
    signUpFrom('203.0.113.9', 'first');
    $user = User::factory()->create(['username' => 'jane']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});
