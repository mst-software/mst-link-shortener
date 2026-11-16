<?php

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->freezeSecond();
});

/**
 * The session data another browser holds after it signed in with the user's current password.
 *
 * @return array<string, string>
 */
function sessionOfAnotherDevice(User $user): array
{
    return ['password_hash_web' => Auth::guard('web')->hashPasswordForCookie($user->password)];
}

/**
 * Forget the current browser so the next request comes from a fresh one.
 */
function switchBrowser(): void
{
    test()->flushSession();
    Auth::forgetGuards();
}

test('changing the password signs out other sessions and keeps the current one', function (bool $isAdvertiser, string $changeRoute, string $dashboardRoute) {
    $user = $isAdvertiser ? User::factory()->advertiser()->create() : User::factory()->create();
    $otherDevice = sessionOfAnotherDevice($user);

    $this->actingAs($user)->withSession($otherDevice)->get(route($dashboardRoute))->assertOk();
    switchBrowser();

    $this->actingAs($user)
        ->post(route($changeRoute), ['current_password' => 'password', 'password' => 'a-new-password'])
        ->assertSessionHasNoErrors();
    $this->get(route($dashboardRoute))->assertOk();
    $this->assertAuthenticatedAs($user);

    switchBrowser();
    $this->actingAs($user->fresh())->withSession($otherDevice)
        ->get(route($dashboardRoute))
        ->assertRedirect(route('login'));
    $this->assertGuest();
})->with([
    'member' => [false, 'member.user.change-password.update', 'member.dashboard'],
    'advertiser' => [true, 'advertiser.profile.password', 'advertiser.dashboard'],
]);

test('a password reset signs out every existing session', function () {
    $user = User::factory()->create();
    $otherDevice = sessionOfAnotherDevice($user);
    $rememberToken = $user->remember_token;

    $this->post(route('password.update'), [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'brand-new-pass',
    ])->assertRedirect(route('login'));

    expect($user->fresh()->remember_token)->not->toBe($rememberToken);

    $this->actingAs($user->fresh())->withSession($otherDevice)
        ->get(route('member.dashboard'))
        ->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a remember me cookie from another device stops working after a password change', function () {
    $user = User::factory()->create(['username' => 'jane']);
    $recaller = Auth::guard('web')->getRecallerName();

    $oldCookie = $this->post(route('login'), ['username' => 'jane', 'password' => 'password', 'remember_me' => '1'])
        ->getCookie($recaller)->getValue();
    switchBrowser();

    $this->withCookie($recaller, $oldCookie)->get(route('member.dashboard'))->assertOk();
    switchBrowser();

    $this->actingAs($user)
        ->post(route('member.user.change-password.update'), ['current_password' => 'password', 'password' => 'a-new-password']);
    switchBrowser();

    $this->withCookie($recaller, $oldCookie)->get(route('member.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the remember me cookie of the current browser keeps working after a password change', function () {
    User::factory()->create(['username' => 'jane']);
    $recaller = Auth::guard('web')->getRecallerName();

    $cookie = $this->post(route('login'), ['username' => 'jane', 'password' => 'password', 'remember_me' => '1'])
        ->getCookie($recaller)->getValue();

    $newCookie = $this->withCookie($recaller, $cookie)
        ->post(route('member.user.change-password.update'), ['current_password' => 'password', 'password' => 'a-new-password'])
        ->assertSessionHasNoErrors()
        ->getCookie($recaller)->getValue();
    switchBrowser();

    $this->withCookie($recaller, $newCookie)->get(route('member.dashboard'))->assertOk();
});

test('a two-factor sign in with a trusted device keeps working', function () {
    $user = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $token = $this->post(route('two-factor.challenge'), ['code' => totpCode($user), 'trust_device' => '1'])
        ->assertRedirect(route('member.dashboard'))
        ->getCookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE)->getValue();
    $this->get(route('member.dashboard'))->assertOk();

    $this->post(route('logout'));
    $this->withCookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE, $token)
        ->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertRedirect(route('member.dashboard'));
    $this->get(route('member.dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('changing the password forgets the trusted devices', function () {
    $user = User::factory()->advertiser()->create();
    TrustedDevice::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('advertiser.profile.password'), ['current_password' => 'password', 'password' => 'a-new-password'])
        ->assertSessionHasNoErrors();

    expect($user->trustedDevices()->count())->toBe(0);
});
