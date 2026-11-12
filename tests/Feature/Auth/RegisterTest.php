<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationData(array $overrides = []): array
{
    return array_merge([
        'username' => 'newmember',
        'email' => 'new@example.com',
        'password' => 'Secret123!',
        'accept' => '1',
    ], $overrides);
}

test('registering creates a user and signs them in', function () {
    $this->post(route('register'), registrationData())
        ->assertRedirect(route('member.dashboard'));

    $user = User::query()->where('username', 'newmember')->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('new@example.com')
        ->and($user->referral_code)->toHaveLength(7);

    $this->assertAuthenticatedAs($user);
});

test('a duplicate username is rejected', function () {
    User::factory()->create(['username' => 'newmember']);

    $this->post(route('register'), registrationData())
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('a duplicate email is rejected', function () {
    User::factory()->create(['email' => 'new@example.com']);

    $this->post(route('register'), registrationData())->assertSessionHasErrors('email');
});

test('a short password is rejected', function () {
    $this->post(route('register'), registrationData(['password' => 'short']))
        ->assertSessionHasErrors('password');
});

test('the terms must be accepted', function () {
    $this->post(route('register'), registrationData(['accept' => null]))
        ->assertSessionHasErrors('accept');
});

test('the username only allows letters, numbers and underscores', function () {
    $this->post(route('register'), registrationData(['username' => 'bad name!']))
        ->assertSessionHasErrors('username');
});

test('the ref cookie is stored as referred_by', function () {
    $referrer = User::factory()->create();

    $this->withCookie('ref', $referrer->referral_code)
        ->post(route('register'), registrationData());

    expect(User::query()->where('username', 'newmember')->value('referred_by'))->toBe($referrer->id);
});

test('an unknown ref cookie leaves referred_by empty', function () {
    $this->withCookie('ref', 'ZZZZZZZ')
        ->post(route('register'), registrationData());

    expect(User::query()->where('username', 'newmember')->value('referred_by'))->toBeNull();
});
