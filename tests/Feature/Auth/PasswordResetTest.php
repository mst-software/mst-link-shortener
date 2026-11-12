<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('a reset link notification is sent to a registered email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('an unknown email gets the same response and no notification', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

test('the new password works after a reset', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee($user->email);

        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-pass',
        ])->assertRedirect(route('login'));

        return true;
    });

    $this->post(route('login'), ['username' => $user->username, 'password' => 'brand-new-pass'])
        ->assertRedirect(route('member.dashboard'));
});

test('an invalid token is rejected', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => 'invalid',
        'email' => $user->email,
        'password' => 'brand-new-pass',
    ])->assertSessionHasErrors('email');
});
