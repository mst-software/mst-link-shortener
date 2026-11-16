<?php

use App\Models\User;
use App\Services\Settings;
use App\Support\SettingsConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the site name setting appears on public pages instead of the default name', function (string $route, string $title) {
    Settings::set('site_name', 'ShortPay');
    SettingsConfigurator::apply();

    $this->get(route($route))
        ->assertOk()
        ->assertSee('<title>'.$title.'</title>', false)
        ->assertSee('Copyright &copy; ShortPay', false)
        ->assertDontSee('LinkCash');
})->with([
    'home' => ['home', 'ShortPay - Best URL Shortener To Earn Money'],
    'terms' => ['terms', 'Terms of Use - ShortPay'],
    'privacy' => ['privacy', 'Privacy Policy - ShortPay'],
    'publisher rates' => ['payout-rates', 'Publisher Rates - ShortPay'],
]);

test('the site name setting appears in the member area title', function () {
    Settings::set('site_name', 'ShortPay');
    SettingsConfigurator::apply();

    $this->actingAs(User::factory()->create())
        ->get(route('member.dashboard'))
        ->assertOk()
        ->assertSee(' - ShortPay</title>', false);
});

test('the legal pages show the support email setting', function (string $route) {
    Settings::set('support_email', 'help@shortpay.test');

    $this->get(route($route))
        ->assertOk()
        ->assertSee('mailto:help@shortpay.test', false)
        ->assertDontSee('yourdomain.com');
})->with(['terms', 'privacy']);

test('without a support email the legal pages point to the contact form', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertDontSee('mailto:', false)
        ->assertDontSee('yourdomain.com')
        ->assertSee(route('home').'#contact', false);
})->with(['terms', 'privacy']);
