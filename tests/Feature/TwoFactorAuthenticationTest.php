<?php

use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\Totp;
use App\Services\TwoFactorAuthentication;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->freezeSecond();
    $this->twoFactor = app(TwoFactorAuthentication::class);
});

test('a valid authenticator code is accepted once', function () {
    $user = User::factory()->withTwoFactor()->create();
    $code = totpCode($user);

    expect($this->twoFactor->verify($user, $code))->toBeTrue()
        ->and($this->twoFactor->verify($user->fresh(), $code))->toBeFalse();
});

test('a code from an older time step is rejected after a newer one was used', function () {
    $user = User::factory()->withTwoFactor()->create();
    $olderCode = totpCode($user);

    $this->travel(Totp::PERIOD_SECONDS)->seconds();
    expect($this->twoFactor->verify($user, totpCode($user)))->toBeTrue()
        ->and($this->twoFactor->verify($user->fresh(), $olderCode))->toBeFalse();
});

test('a wrong code is rejected', function () {
    $user = User::factory()->withTwoFactor()->create();
    expect($this->twoFactor->verify($user, wrongTotpCode($user)))->toBeFalse()
        ->and($this->twoFactor->verify($user, 'not-a-code'))->toBeFalse();
});

test('a recovery code works once and is removed', function () {
    $user = User::factory()->withTwoFactor()->create();
    $code = UserFactory::RECOVERY_CODES[0];

    expect($this->twoFactor->verify($user, $code))->toBeTrue()
        ->and($this->twoFactor->verify($user->fresh(), $code))->toBeFalse()
        ->and($user->fresh()->two_factor_recovery_codes)->toHaveCount(count(UserFactory::RECOVERY_CODES) - 1);
});

test('codes are rejected while two-factor is not confirmed', function () {
    $user = User::factory()->withPendingTwoFactor()->create();

    expect($this->twoFactor->verify($user, totpCode($user)))->toBeFalse();
});

test('confirming with a valid code turns two-factor on and returns eight recovery codes', function () {
    $user = User::factory()->withPendingTwoFactor()->create();

    $codes = $this->twoFactor->confirm($user, totpCode($user));
    $user->refresh();

    expect($codes)->toHaveCount(8)
        ->and($user->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->two_factor_recovery_codes)->toHaveCount(8)
        ->and(Hash::check($codes[0], $user->two_factor_recovery_codes[0]))->toBeTrue()
        ->and($user->two_factor_recovery_codes)->not->toContain($codes[0]);
});

test('ten wrong codes lock the account out of code checks for fifteen minutes', function () {
    $user = User::factory()->withTwoFactor()->create();

    foreach (range(1, 10) as $attempt) {
        expect($this->twoFactor->verify($user, wrongTotpCode($user)))->toBeFalse();
    }

    expect(fn () => $this->twoFactor->verify($user, totpCode($user)))
        ->toThrow(ValidationException::class, 'Too many wrong authentication codes');

    $this->travel(15)->minutes();
    $this->travel(1)->seconds();

    expect($this->twoFactor->verify($user, totpCode($user)))->toBeTrue();
});

test('only wrong codes count towards the lockout', function () {
    $user = User::factory()->withTwoFactor()->create();

    foreach (range(1, 9) as $attempt) {
        $this->twoFactor->verify($user, wrongTotpCode($user));
    }

    expect($this->twoFactor->verify($user, totpCode($user)))->toBeTrue();

    $this->travel(Totp::PERIOD_SECONDS)->seconds();
    expect($this->twoFactor->verify($user, totpCode($user)))->toBeTrue();
});

test('wrong codes while confirming the setup count towards the lockout', function () {
    $user = User::factory()->withPendingTwoFactor()->create();

    foreach (range(1, 10) as $attempt) {
        expect($this->twoFactor->confirm($user, wrongTotpCode($user)))->toBeNull();
    }

    expect(fn () => $this->twoFactor->confirm($user, totpCode($user)))->toThrow(ValidationException::class);
    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('the code used to confirm the setup cannot be used again', function () {
    $user = User::factory()->withPendingTwoFactor()->create();
    $code = totpCode($user);

    expect($this->twoFactor->confirm($user, $code))->toHaveCount(8)
        ->and($this->twoFactor->confirm($user->fresh(), $code))->toBeNull()
        ->and($this->twoFactor->verify($user->fresh(), $code))->toBeFalse();
});

test('a setup confirmed elsewhere in the meantime is not confirmed again', function () {
    $user = User::factory()->withPendingTwoFactor()->create();
    $stale = $user->fresh();
    $this->twoFactor->confirm($user, totpCode($user));

    $this->travel(Totp::PERIOD_SECONDS)->seconds();

    expect($this->twoFactor->confirm($stale, totpCode($stale)))->toBeNull();
});

test('regenerating the recovery codes forgets the trusted devices', function () {
    $user = User::factory()->withTwoFactor()->create();
    TrustedDevice::factory()->for($user)->create();

    $codes = $this->twoFactor->regenerateRecoveryCodes($user);

    expect($user->trustedDevices()->count())->toBe(0)
        ->and(Hash::check($codes[0], $user->fresh()->two_factor_recovery_codes[0]))->toBeTrue();
});

test('the secret is stored encrypted', function () {
    $user = User::factory()->withTwoFactor()->create();

    $stored = User::query()->toBase()->where('id', $user->id)->value('two_factor_secret');

    expect($stored)->not->toBe($user->two_factor_secret)
        ->and(decrypt($stored, false))->toBe($user->two_factor_secret);
});

test('the secret and recovery codes are hidden from arrays and json', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect($user->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes'])
        ->and($user->toJson())->not->toContain((string) $user->two_factor_secret);
});
