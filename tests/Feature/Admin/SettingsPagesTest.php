<?php

use App\Enums\SettingsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins can open every settings page with the settings sub-navigation', function (SettingsPage $page) {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route($page->routeName()))
        ->assertOk();

    foreach (SettingsPage::cases() as $other) {
        $response->assertSee(route($other->routeName()), false);
    }
})->with(SettingsPage::cases());

test('members cannot open a settings page', function (SettingsPage $page) {
    $this->actingAs(User::factory()->create())
        ->get(route($page->routeName()))
        ->assertForbidden();
})->with(SettingsPage::cases());

test('guests are sent to the sign in page', function (SettingsPage $page) {
    $this->get(route($page->routeName()))->assertRedirect(route('login'));
})->with(SettingsPage::cases());

test('the admin sidebar has a single settings entry that stays active on every settings page', function () {
    $html = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.settings.captcha.edit'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('#<li class="active">\s*<a href="'.preg_quote(route('admin.settings.edit'), '#').'">#')
        ->and(substr_count($html, 'href="'.route('admin.settings.edit').'"'))->toBe(1);
});

test('the email and payments pages tell operators to restart the queue workers', function (string $route) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route($route))
        ->assertOk()
        ->assertSee('php artisan queue:restart');
})->with(['admin.settings.email.edit', 'admin.settings.payments.edit']);
