<?php

use App\Models\User;
use App\Services\Totp;
use App\Services\TwoFactorAuthentication;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
