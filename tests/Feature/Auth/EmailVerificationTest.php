<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * A signed verification link for the given user.
 */
function verificationUrl(User $user, ?string $hash = null, int $minutes = 60): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
        'id' => $user->getKey(),
        'hash' => $hash ?? sha1($user->getEmailForVerification()),
    ]);
}

test('the notice page is shown to an unverified user', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee($user->email)
        ->assertSee('Resend verification email');
});

test('a verified user is redirected from the notice page to their own dashboard', function (string $state, string $dashboardRoute) {
    $user = $state === 'member' ? User::factory()->create() : User::factory()->{$state}()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertRedirect(route($dashboardRoute));
})->with([
    'member' => ['member', 'member.dashboard'],
    'advertiser' => ['advertiser', 'advertiser.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);

test('the notice page links an unverified admin to the admin dashboard', function () {
    $this->actingAs(User::factory()->admin()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('href="'.route('admin.dashboard').'"', false);
});

test('a guest is sent to sign in from the notice page', function () {
    $this->get(route('verification.notice'))->assertRedirect(route('login'));
});

test('a valid signed link verifies the email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationUrl($user))
        ->assertRedirect(route('member.dashboard'))
        ->assertSessionHas('success');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a link with a tampered signature is rejected', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationUrl($user).'tampered')
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an expired link is rejected', function () {
    $user = User::factory()->unverified()->create();
    $url = verificationUrl($user);

    $this->travel(61)->minutes();

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a link for a different email hash is rejected', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(verificationUrl($user, sha1('other@example.com')))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a link for another user is rejected', function () {
    $owner = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();

    $this->actingAs($other)
        ->get(verificationUrl($owner))
        ->assertForbidden();

    expect($owner->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('resending sends a new verification mail', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('success');

    Notification::assertSentToTimes($user, VerifyEmail::class, 1);
});

test('resending is throttled after six attempts a minute', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    foreach (range(1, 6) as $attempt) {
        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();
    }

    $this->actingAs($user)->post(route('verification.send'))->assertTooManyRequests();

    Notification::assertSentToTimes($user, VerifyEmail::class, 6);
});

test('a verified user does not get another verification mail', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('member.dashboard'));

    Notification::assertNothingSent();
});
