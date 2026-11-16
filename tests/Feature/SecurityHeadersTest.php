<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::get('testing/headers', fn () => 'ok');
    Route::get('testing/own-frame-header', fn () => response('ok')->header('X-Frame-Options', 'DENY'));
});

it('sends the hardening headers on public pages', function () {
    $response = $this->get(route('home'))->assertOk();

    foreach (SecurityHeaders::HEADERS as $name => $value) {
        $response->assertHeader($name, $value);
    }

    $response->assertHeaderMissing('Strict-Transport-Security');
});

it('sends the hardening headers on error pages and json answers', function () {
    $this->get('testing/does/not/exist')->assertNotFound()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $this->postJson(route('widget.start'))->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('keeps a header the response already set', function () {
    $this->get('testing/own-frame-header')->assertHeader('X-Frame-Options', 'DENY');
});

it('sends HSTS only over https in production', function (string $environment, string $url, bool $expectsHsts) {
    $this->app['env'] = $environment;

    $response = $this->get($url)->assertOk();

    $expectsHsts
        ? $response->assertHeader('Strict-Transport-Security', SecurityHeaders::HSTS)
        : $response->assertHeaderMissing('Strict-Transport-Security');
})->with([
    'production over https' => ['production', 'https://localhost/testing/headers', true],
    'production over http' => ['production', 'http://localhost/testing/headers', false],
    'local over https' => ['local', 'https://localhost/testing/headers', false],
]);
