<?php

use App\Enums\PayoutMethod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates an api token and a referral code on creation', function () {
    $user = User::factory()->create();

    expect($user->api_token)->toHaveLength(24)
        ->and($user->referral_code)->toHaveLength(7)
        ->and($user->role)->toBe(UserRole::Member)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->balance_micro)->toBe(0)
        ->and($user->processing_micro)->toBe(0)
        ->and($user->default_level)->toBe(1);
});

it('keeps an explicitly given api token and referral code', function () {
    $user = User::factory()->create(['api_token' => str_repeat('a', 24), 'referral_code' => 'ABC1234']);

    expect($user->api_token)->toBe(str_repeat('a', 24))
        ->and($user->referral_code)->toBe('ABC1234');
});

it('generates unique tokens and codes for different users', function () {
    $users = User::factory()->count(5)->create();

    expect($users->pluck('api_token')->unique())->toHaveCount(5)
        ->and($users->pluck('referral_code')->unique())->toHaveCount(5);
});

it('identifies admins', function () {
    expect(User::factory()->admin()->create()->isAdmin())->toBeTrue()
        ->and(User::factory()->create()->isAdmin())->toBeFalse();
});

it('identifies active users', function () {
    expect(User::factory()->create()->isActive())->toBeTrue()
        ->and(User::factory()->banned()->create()->isActive())->toBeFalse();
});

it('links a user to the referrer who invited them', function () {
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by' => $referrer->id]);

    expect($referred->referrer->is($referrer))->toBeTrue()
        ->and($referrer->referrals)->toHaveCount(1)
        ->and($referrer->referrals->first()->is($referred))->toBeTrue()
        ->and($referrer->referrer)->toBeNull();
});

it('hashes the password and casts payout details', function () {
    $user = User::factory()->create(['password' => 'secret-pass', 'payout_details' => ['account' => 'a@b.c']]);

    expect($user->password)->not->toBe('secret-pass')
        ->and($user->fresh()->payout_details)->toBe(['account' => 'a@b.c']);
});

it('provides a label for every enum case', function () {
    expect(UserRole::Admin->label())->toBe('Admin')
        ->and(UserStatus::Banned->label())->toBe('Banned')
        ->and(PayoutMethod::BankTransfer->label())->toBe('Bank Transfer')
        ->and(PayoutMethod::Usdt->label())->toBe('USDT');
});
