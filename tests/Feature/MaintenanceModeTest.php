<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\Link;
use App\Models\User;
use App\Services\Settings;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
    $this->actingAs(User::factory()->create())
        ->getJson(route('member.dashboard'))
        ->assertStatus(503)
        ->assertJson(['success' => false]);
});

test('widget calls get a readable json 503 during maintenance', function () {
    $this->postJson(route('widget.start'), [])
        ->assertStatus(503)
        ->assertExactJson(['error' => 'maintenance'])
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

test('the shorten api answers with a json 503 during maintenance', function (string $path) {
    $owner = User::factory()->create();

    $this->get("{$path}?api={$owner->api_token}&url=https://example.com/a")
        ->assertStatus(503)
        ->assertJson(['status' => 'error'])
        ->assertHeader('Access-Control-Allow-Origin', '*');

    expect(Link::query()->count())->toBe(0);
})->with(['/api', '/api-shorten/v2']);

test('an advertiser coming back from PayPal is not stopped by maintenance', function (string $route) {
    $advertiser = User::factory()->advertiser()->create();

    $response = $this->actingAs($advertiser)->get(route($route, ['token' => 'unknown-order']));

    expect($response->getStatusCode())->not->toBe(503);
})->with(['advertiser.topups.paypal.return', 'advertiser.topups.paypal.cancel']);

test('password reset pages stay open during maintenance', function () {
    $this->get(route('password.request'))->assertOk();
    $this->get(route('password.reset', ['token' => 'abc']))->assertOk();
});

test('an admin with two-factor can sign in with an authenticator code during maintenance', function () {
    $this->freezeSecond();
    $admin = User::factory()->admin()->withTwoFactor()->create(['username' => 'boss']);

    $this->post(route('login'), ['username' => 'boss', 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));
    $this->get(route('two-factor.challenge'))->assertOk();
    $this->post(route('two-factor.challenge'), ['code' => totpCode($admin)])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
    $this->get(route('admin.dashboard'))->assertOk();
});

test('an admin with two-factor can sign in with a recovery code during maintenance', function () {
    $admin = User::factory()->admin()->withTwoFactor()->create(['username' => 'boss']);

    $this->post(route('login'), ['username' => 'boss', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => UserFactory::RECOVERY_CODES[0]])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
});

test('a member with two-factor gets the maintenance page after the challenge', function () {
    $this->freezeSecond();
    $member = User::factory()->withTwoFactor()->create(['username' => 'jane']);

    $this->post(route('login'), ['username' => 'jane', 'password' => 'password']);
    $this->post(route('two-factor.challenge'), ['code' => totpCode($member)]);

    $this->get(route('member.dashboard'))->assertStatus(503);
});

test('signed-in admins reach the admin users and audit log pages during maintenance', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.audit-logs.index'))->assertOk();
});

test('an unreachable settings store does not make the maintenance check fail the request', function () {
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('cache down'));

    $response = (new CheckMaintenanceMode)->handle(Request::create('/'), fn () => response('passed'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('passed');
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
