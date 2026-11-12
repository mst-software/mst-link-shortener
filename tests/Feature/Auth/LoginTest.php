<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a member can sign in with their username', function () {
    $user = User::factory()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertRedirect(route('member.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('a member can sign in with their email', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->post(route('login'), ['username' => 'jane@example.com', 'password' => 'password'])
        ->assertRedirect(route('member.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('a wrong password is rejected', function () {
    User::factory()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'wrong-password'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('a banned user cannot sign in', function () {
    User::factory()->banned()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertSessionHasErrors(['username' => 'This account is disabled.']);

    $this->assertGuest();
});

test('five failed attempts lock the sign in temporarily', function () {
    User::factory()->create(['username' => 'jane']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['username' => 'jane', 'password' => 'wrong-password']);
    }

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('a member can sign out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
