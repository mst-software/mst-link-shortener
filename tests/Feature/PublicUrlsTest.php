<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('public urls', [
    'login' => ['login', '/auth/signin'],
    'register' => ['register', '/auth/signup'],
    'forgot password' => ['password.request', '/auth/forgot-password'],
    'privacy' => ['privacy', '/pages/privacy'],
    'terms' => ['terms', '/pages/terms'],
    'payout rates' => ['payout-rates', '/payout-rates'],
    'blog' => ['blog', '/blog'],
]);

test('public page is served at the reference url', function (string $name, string $path) {
    expect(route($name, absolute: false))->toBe($path);

    $this->get($path)->assertOk();
})->with('public urls');

test('old public urls return 404', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/login', '/register', '/privacy', '/terms', '/forgot-password']);

test('logout url exists and redirects home', function () {
    expect(route('logout', absolute: false))->toBe('/auth/signout');

    $this->post('/auth/signout')->assertRedirect(route('home'));
});

test('logout rejects GET requests', function () {
    $this->get('/auth/signout')->assertStatus(405);
});

test('referral link stores the code in a 30 day cookie and redirects to signup', function () {
    $response = $this->withUnencryptedCookies([])->get('/ref/6tpCb0n');

    $response->assertRedirect(route('register'));
    $response->assertCookie('ref', '6tpCb0n');

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'ref');
    expect($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp())
        ->toBeLessThanOrEqual(now()->addDays(30)->addMinute()->getTimestamp());
});

test('referral link with an invalid code redirects without a cookie', function (string $code) {
    $this->get('/ref/'.$code)
        ->assertRedirect(route('register'))
        ->assertCookieMissing('ref');
})->with(['short' => 'abc', 'too long' => '6tpCb0n1', 'symbols' => '6tpCb-n']);

test('footer links point to the new urls', function () {
    $this->get(route('home'))
        ->assertSee('href="'.url('/pages/privacy').'"', false)
        ->assertSee('href="'.url('/auth/signin').'"', false)
        ->assertSee('href="'.url('/auth/signup').'"', false);
});
