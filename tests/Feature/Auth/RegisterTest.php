<?php

use App\Models\User;
use App\Services\Settings;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;

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

test('registering sends a verification mail and leaves the email unverified', function () {
    Notification::fake();

    $this->post(route('register'), registrationData());

    $user = User::query()->where('username', 'newmember')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('registering still succeeds when the verification mail cannot be sent', function () {
    Exceptions::fake();
    Notification::shouldReceive('send')->andThrow(new TransportException('Mail server is down.'));

    $this->post(route('register'), registrationData())
        ->assertRedirect(route('member.dashboard'));

    $this->assertAuthenticatedAs(User::query()->where('username', 'newmember')->sole());
    Exceptions::assertReported(TransportException::class);
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

test('closed sign-ups show a message instead of the sign-up forms', function (string $route) {
    Settings::set('registration_open', '0');

    $this->get(route($route))
        ->assertOk()
        ->assertSee('Sign-ups are closed at the moment. Please check back later.')
        ->assertDontSee('id="signup-form"', false);
})->with(['register', 'advertiser.register']);

test('closed sign-ups refuse new members and advertisers', function (string $route) {
    Settings::set('registration_open', '0');

    $this->post(route($route), registrationData())
        ->assertForbidden()
        ->assertSee('Sign-ups are closed at the moment.');

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
})->with(['register', 'advertiser.register']);

test('open sign-ups show the sign-up forms', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('id="signup-form"', false)
        ->assertDontSee('Sign-ups are closed');
})->with(['register', 'advertiser.register']);
