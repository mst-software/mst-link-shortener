<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Models\Link;
use App\Models\User;

test('visit routes are limited to 60 requests per minute per ip', function () {
    $link = Link::factory()->create();

    foreach (range(1, 60) as $attempt) {
        $this->get('/'.$link->alias)->assertRedirect();
    }

    $this->get('/'.$link->alias)->assertStatus(429);
});

test('registration is limited to 5 attempts per minute per ip', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/auth/signup', [])->assertSessionHasErrors();
    }

    $this->post('/auth/signup', [])->assertStatus(429);
});

test('actions that check the current password are limited to 5 attempts per minute', function (bool $isAdvertiser, string $route, array $payload) {
    $user = $isAdvertiser ? User::factory()->advertiser()->create() : User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)->post(route($route), [...$payload, 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
    }

    $this->actingAs($user)->post(route($route), [...$payload, 'current_password' => 'password'])->assertStatus(429);
})->with([
    'api token' => [false, 'member.tools.api.regenerate-token', []],
    'member password' => [false, 'member.user.change-password.update', ['password' => 'a-new-password']],
    'member email' => [false, 'member.user.change-email.update', ['email' => 'fresh@example.com']],
    'advertiser password' => [true, 'advertiser.profile.password', ['password' => 'a-new-password']],
    'advertiser email' => [true, 'advertiser.profile.email', ['email' => 'fresh@example.com']],
]);
