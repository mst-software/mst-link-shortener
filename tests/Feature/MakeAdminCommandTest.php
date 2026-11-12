<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the command creates an administrator', function () {
    $this->artisan('make:admin', ['username' => 'boss', 'email' => 'boss@example.com', '--password' => 'long-enough-pass'])
        ->assertSuccessful();

    $user = User::query()->where('username', 'boss')->first();

    expect($user->isAdmin())->toBeTrue()
        ->and($user->email)->toBe('boss@example.com');
});

test('the command asks for the password when it is missing', function () {
    $this->artisan('make:admin', ['username' => 'boss', 'email' => 'boss@example.com'])
        ->expectsQuestion('Password', 'long-enough-pass')
        ->assertSuccessful();

    expect(User::query()->where('username', 'boss')->exists())->toBeTrue();
});

test('the command rejects a duplicate username', function () {
    User::factory()->create(['username' => 'boss']);

    $this->artisan('make:admin', ['username' => 'boss', 'email' => 'other@example.com', '--password' => 'long-enough-pass'])
        ->assertFailed();

    expect(User::query()->where('username', 'boss')->count())->toBe(1);
});
