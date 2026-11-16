<?php

use App\Enums\LoginEvent;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserLogin;
use App\Services\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const FOOTPRINT_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) FootprintTest/1.0';

beforeEach(function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeader('User-Agent', FOOTPRINT_UA);
});

test('a member sign up records one signup footprint', function () {
    $this->post(route('register'), ['username' => 'newmember', 'email' => 'new@example.com', 'password' => 'Secret123!', 'accept' => '1'])
        ->assertRedirect(route('member.dashboard'));

    $footprint = UserLogin::query()->sole();

    expect($footprint->user_id)->toBe(User::query()->where('username', 'newmember')->value('id'))
        ->and($footprint->event)->toBe(LoginEvent::Signup)
        ->and($footprint->ip)->toBe('203.0.113.7')
        ->and($footprint->ua_hash)->toBe(hash('sha256', FOOTPRINT_UA))
        ->and($footprint->user_agent)->toBe(FOOTPRINT_UA)
        ->and($footprint->country)->toBeNull();
});

test('an advertiser sign up records a signup footprint', function () {
    $this->post(route('advertiser.register'), ['username' => 'brand', 'email' => 'brand@example.com', 'password' => 'Secret123!', 'accept' => '1'])
        ->assertRedirect(route('advertiser.dashboard'));

    expect(UserLogin::query()->sole()->event)->toBe(LoginEvent::Signup);
});

test('a password sign in records a login footprint', function () {
    $user = User::factory()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);

    $footprint = UserLogin::query()->sole();

    expect($footprint->user_id)->toBe($user->id)
        ->and($footprint->event)->toBe(LoginEvent::Login)
        ->and($footprint->ip)->toBe('203.0.113.7');
});

test('a failed sign in records nothing', function () {
    User::factory()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'wrong']);

    expect(UserLogin::query()->count())->toBe(0);
});

test('a two-factor sign in records the footprint only after the code step', function () {
    $this->freezeSecond();
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);

    expect(UserLogin::query()->count())->toBe(0);

    $this->post(route('two-factor.challenge'), ['code' => totpCode($user)]);

    expect(UserLogin::query()->sole())->user_id->toBe($user->id)->event->toBe(LoginEvent::Login);
});

test('a sign in from a trusted device records a login footprint', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);
    TrustedDevice::factory()->for($user)->withToken('device-token')->create();

    $this->withCookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE, 'device-token')
        ->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertRedirect(route('member.dashboard'));

    expect(UserLogin::query()->sole())->user_id->toBe($user->id)->event->toBe(LoginEvent::Login);
});

test('a long user agent is trimmed and hashed in full', function () {
    $userAgent = str_repeat('a', 600);
    User::factory()->create(['username' => 'jane']);

    $this->withHeader('User-Agent', $userAgent)->post(route('login'), ['username' => 'jane', 'password' => 'password']);

    $footprint = UserLogin::query()->sole();

    expect(mb_strlen($footprint->user_agent))->toBe(255)
        ->and($footprint->ua_hash)->toBe(hash('sha256', $userAgent));
});
