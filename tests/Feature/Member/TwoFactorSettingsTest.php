<?php

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->freezeSecond();
});

test('starting the setup shows the secret but does not turn two-factor on', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('member.user.two-factor.store'))
        ->assertRedirect(route('member.user.two-factor'));

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();

    $this->get(route('member.user.two-factor'))
        ->assertOk()
        ->assertSee(trim(chunk_split($user->two_factor_secret, 4, ' ')))
        ->assertSee('otpauth://totp/', false);
});

test('starting the setup again does not replace an active secret', function () {
    $user = User::factory()->withTwoFactor()->create();
    $secret = $user->two_factor_secret;

    $this->actingAs($user)->post(route('member.user.two-factor.store'));

    expect($user->refresh()->two_factor_secret)->toBe($secret);
});

test('a valid code turns two-factor on and shows the recovery codes once', function () {
    $user = User::factory()->withPendingTwoFactor()->create();

    $response = $this->actingAs($user)
        ->post(route('member.user.two-factor.confirm'), ['code' => totpCode($user)])
        ->assertRedirect(route('member.user.two-factor'))
        ->assertSessionHas('recoveryCodes');

    $recoveryCodes = $response->getSession()->get('recoveryCodes');

    expect($recoveryCodes)->toHaveCount(8)
        ->and($user->refresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->get(route('member.user.two-factor'))->assertSee($recoveryCodes[0]);
    $this->get(route('member.user.two-factor'))->assertDontSee($recoveryCodes[0]);
});

test('a wrong code does not turn two-factor on', function () {
    $user = User::factory()->withPendingTwoFactor()->create();

    $this->actingAs($user)
        ->post(route('member.user.two-factor.confirm'), ['code' => wrongTotpCode($user)])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('turning two-factor off needs the current password and a valid code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->delete(route('member.user.two-factor.destroy'), ['current_password' => 'password', 'code' => totpCode($user)])
        ->assertRedirect(route('member.user.two-factor'))
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull();
});

test('turning two-factor off is refused with a wrong password or code', function (string $password, ?string $code, string $errorField) {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->delete(route('member.user.two-factor.destroy'), ['current_password' => $password, 'code' => $code ?? 'wrong-code'])
        ->assertSessionHasErrors($errorField);

    expect($user->refresh()->hasTwoFactorEnabled())->toBeTrue();
})->with([
    'wrong password' => ['wrong', UserFactory::RECOVERY_CODES[0], 'current_password'],
    'wrong code' => ['password', null, 'code'],
]);

test('a wrong password does not use up the recovery code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)->delete(route('member.user.two-factor.destroy'), [
        'current_password' => 'wrong',
        'code' => UserFactory::RECOVERY_CODES[0],
    ]);

    expect($user->refresh()->two_factor_recovery_codes)->toHaveCount(count(UserFactory::RECOVERY_CODES));
});

test('recovery codes can be regenerated and the old ones stop working', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this->actingAs($user)
        ->post(route('member.user.two-factor.recovery-codes'), ['current_password' => 'password', 'code' => UserFactory::RECOVERY_CODES[0]])
        ->assertRedirect(route('member.user.two-factor'));

    expect($response->getSession()->get('recoveryCodes'))->toHaveCount(8);

    $this->delete(route('member.user.two-factor.destroy'), ['current_password' => 'password', 'code' => UserFactory::RECOVERY_CODES[1]])
        ->assertSessionHasErrors('code');
});

test('code attempts on the settings page are rate limited', function () {
    $user = User::factory()->withTwoFactor()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)->delete(route('member.user.two-factor.destroy'), ['current_password' => 'password', 'code' => 'wrong-code']);
    }

    $this->actingAs($user)
        ->delete(route('member.user.two-factor.destroy'), ['current_password' => 'password', 'code' => totpCode($user)])
        ->assertTooManyRequests();

    expect($user->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('turning two-factor off forgets the trusted devices', function () {
    $user = User::factory()->withTwoFactor()->create();
    TrustedDevice::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('member.user.two-factor.destroy'), ['current_password' => 'password', 'code' => totpCode($user)])
        ->assertCookieExpired(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE);

    expect($user->trustedDevices()->count())->toBe(0);
});

test('regenerating the recovery codes forgets the trusted devices and their cookie', function () {
    $user = User::factory()->withTwoFactor()->create();
    TrustedDevice::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('member.user.two-factor.recovery-codes'), ['current_password' => 'password', 'code' => totpCode($user)])
        ->assertCookieExpired(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE);

    expect($user->trustedDevices()->count())->toBe(0);
});

test('changing the password forgets the trusted devices', function () {
    $user = User::factory()->withTwoFactor()->create();
    TrustedDevice::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('member.user.change-password.update'), ['current_password' => 'password', 'password' => 'a-new-password'])
        ->assertSessionHasNoErrors();

    expect($user->trustedDevices()->count())->toBe(0);
});

test('an admin can open the two-factor page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('member.user.two-factor'))
        ->assertOk();
});
