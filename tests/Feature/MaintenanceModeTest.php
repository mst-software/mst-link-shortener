<?php

use App\Models\Link;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::set('maintenance_mode', '1');
});

test('visitors see the maintenance page while maintenance mode is on', function (string $route) {
    Settings::set('maintenance_message', 'Back at 10:00 UTC.');

    $this->get(route($route))
        ->assertStatus(503)
        ->assertHeader('Retry-After', '3600')
        ->assertSee('Down for maintenance')
        ->assertSee('Back at 10:00 UTC.')
        ->assertDontSee('class="site-notice"', false);
})->with(['home', 'register', 'terms']);

test('short links do not open during maintenance', function () {
    $link = Link::factory()->create();

    $this->get(route('visit.start', $link->alias))->assertStatus(503);
});

test('members see the maintenance page during maintenance', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('member.dashboard'))
        ->assertStatus(503);
});

test('json requests get a json error during maintenance', function () {
    $this->postJson(route('widget.start'), [])
        ->assertStatus(503)
        ->assertJson(['success' => false]);
});

test('the sign in page stays open so an admin can get in', function () {
    $this->get(route('login'))->assertOk();
});

test('an admin can sign in during maintenance', function () {
    $admin = User::factory()->admin()->create(['username' => 'boss']);

    $this->post(route('login'), ['username' => 'boss', 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticatedAs($admin);
});

test('admins keep using the site during maintenance', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('home'))->assertOk();
    $this->actingAs($admin)->get(route('admin.settings.general.edit'))->assertOk();
});

test('the site works normally when maintenance mode is off', function () {
    Settings::set('maintenance_mode', '0');

    $this->get(route('home'))->assertOk();
});
