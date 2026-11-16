<?php

use App\Enums\UserStatus;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->freezeSecond();
});

test('a correct password does not sign a two-factor user in yet', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    $this->get(route('two-factor.challenge'))->assertOk()->assertSee('authenticator app');
    $this->get(route('member.dashboard'))->assertRedirect(route('login'));
});

test('a valid authenticator code completes the sign in', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);

    $this->post(route('two-factor.challenge'), ['code' => totpCode($user)])
        ->assertRedirect(route('member.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('the challenge keeps the intended url and the role redirect', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['username' => 'boss']);

    $this->post(route('login'), ['username' => 'boss', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => totpCode($admin)])
        ->assertRedirect(route('admin.dashboard'));

    $this->post(route('logout'));

    $this->get(route('member.withdraws'))->assertRedirect(route('login'));
    $this->post(route('login'), ['username' => 'boss', 'password' => 'password']);
    $this->travel(31)->seconds();
    $this->post(route('two-factor.challenge'), ['code' => totpCode($admin)])
        ->assertRedirect(route('member.withdraws'));
});

test('the remember me choice is carried through the challenge', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password', 'remember_me' => '1']);
    $response = $this->post(route('two-factor.challenge'), ['code' => totpCode($user)]);

    expect(collect($response->headers->getCookies())->contains(
        fn ($cookie): bool => str_starts_with($cookie->getName(), 'remember_web_'),
    ))->toBeTrue();
});

test('a recovery code completes the sign in only once', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);
    $recoveryCode = UserFactory::RECOVERY_CODES[0];

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => $recoveryCode])->assertRedirect(route('member.dashboard'));
    $this->post(route('logout'));

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => $recoveryCode])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a wrong code is rejected', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => wrongTotpCode($user)])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a code that was already used cannot sign in again', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);
    $code = totpCode($user);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect(route('member.dashboard'));
    $this->post(route('logout'));

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('the pending sign in expires after five minutes', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $this->travel(5)->minutes();
    $this->travel(1)->seconds();

    $this->post(route('two-factor.challenge'), ['code' => totpCode($user)])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('the challenge page needs a pending sign in', function () {
    $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    $this->post(route('two-factor.challenge'), ['code' => '123456'])->assertRedirect(route('login'));

    $this->assertGuest();
});

test('challenge attempts are limited to five a minute', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('two-factor.challenge'), ['code' => wrongTotpCode($user)]);
    }

    $this->post(route('two-factor.challenge'), ['code' => totpCode($user)])->assertTooManyRequests();

    $this->assertGuest();
});

test('ten wrong codes from any ip address lock the account out of the challenge', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('login'), ['username' => 'jane', 'password' => 'password']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('two-factor.challenge'), ['code' => wrongTotpCode($user)]);
        }
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => totpCode($user)])
        ->assertSessionHasErrors(['code' => 'Too many wrong authentication codes. Please try again in 15 minute(s).']);

    $this->assertGuest();
});

test('a banned user cannot finish the challenge', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $user->forceFill(['status' => UserStatus::Banned])->save();

    $this->post(route('two-factor.challenge'), ['code' => totpCode($user)])->assertRedirect(route('login'));

    $this->assertGuest();
});

test('remembering the device stores only a hash and lets the next sign in skip the code', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $response = $this->post(route('two-factor.challenge'), ['code' => totpCode($user), 'trust_device' => '1'])
        ->assertCookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE);

    $token = $response->getCookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE)->getValue();
    $device = TrustedDevice::query()->sole();

    expect($device->user_id)->toBe($user->id)
        ->and($device->token_hash)->toBe(hash('sha256', $token))
        ->and($device->expires_at->equalTo(now()->addDays(30)))->toBeTrue();

    $this->post(route('logout'));
    $this->withCookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE, $token)
        ->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertRedirect(route('member.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('an expired or foreign trusted device still needs the code', function (string $state) {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);
    $owner = $state === 'foreign' ? User::factory()->withTwoFactor()->create() : $user;
    $factory = TrustedDevice::factory()->for($owner)->withToken('device-token');
    ($state === 'expired' ? $factory->expired() : $factory)->create();

    $this->withCookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE, 'device-token')
        ->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
})->with(['expired', 'foreign']);

test('a trusted device does not skip the password', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);
    TrustedDevice::factory()->for($user)->withToken('device-token')->create();

    $this->withCookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE, 'device-token')
        ->post(route('login'), ['username' => 'jane', 'password' => 'wrong-password'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});
