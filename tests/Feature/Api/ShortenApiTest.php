<?php

use App\Enums\UserStatus;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.url' => 'https://linkcash.test']);
    $this->owner = User::factory()->create(['default_level' => 3]);
});

it('shortens a url on both api paths', function (string $path) {
    $this->getJson("{$path}?api={$this->owner->api_token}&url=https://example.com/a")
        ->assertOk()
        ->assertJson(['status' => 'success', 'message' => ''])
        ->assertJsonStructure(['shortenedUrl']);
})->with(['/api', '/api-shorten/v2']);

it('uses the token owner default level when no level is given', function () {
    $this->getJson("/api?api={$this->owner->api_token}&url=https://example.com/a")->assertOk();

    expect(Link::query()->where('user_id', $this->owner->id)->first()->level)->toBe(3);
});

it('honours an explicit level and alias', function () {
    $this->getJson("/api?api={$this->owner->api_token}&url=https://example.com/a&level=2&alias=custom-one")->assertOk();

    expect(Link::query()->where('alias', 'custom-one')->first()->level)->toBe(2);
});

it('accepts an optional password and expiry date', function () {
    $expiresAt = now()->addDays(2)->format('Y-m-d H:i');

    $this->getJson('/api-shorten/v2?'.http_build_query([
        'api' => $this->owner->api_token,
        'url' => 'https://example.com/a',
        'alias' => 'api-locked',
        'password' => 'open-sesame',
        'expires_at' => $expiresAt,
    ]))->assertOk()->assertJson(['status' => 'success']);

    $link = Link::query()->where('alias', 'api-locked')->first();

    expect(Hash::check('open-sesame', $link->password))->toBeTrue()
        ->and($link->expires_at->format('Y-m-d H:i'))->toBe($expiresAt);
});

it('returns an api error for invalid link options', function (array $options, string $message) {
    $query = http_build_query(['api' => $this->owner->api_token, 'url' => 'https://example.com/a', ...$options]);

    $this->getJson('/api?'.$query)
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertExactJson(['status' => 'error', 'message' => $message]);

    expect(Link::query()->count())->toBe(0);
})->with([
    'short password' => [['password' => 'abc'], 'The password field must be at least 4 characters.'],
    'password over 72 bytes' => [['password' => str_repeat('é', 40)], 'The password must not be longer than 72 bytes.'],
    'past expiry' => [['expires_at' => '2020-01-01'], 'The expiry date must be in the future.'],
]);

it('returns a plain text validation error for format=text', function () {
    $response = $this->get("/api?api={$this->owner->api_token}&url=https://example.com/a&expires_at=2020-01-01&format=text");

    expect($response->getContent())->toBe('The expiry date must be in the future.');
});

it('returns a json error for an unknown token', function () {
    $this->getJson('/api?api=unknown&url=https://example.com/a')
        ->assertJson(['status' => 'error', 'message' => 'Invalid API token.']);
});

it('returns a json error for a banned user', function () {
    $banned = User::factory()->create(['status' => UserStatus::Banned]);

    $this->getJson("/api?api={$banned->api_token}&url=https://example.com/a")
        ->assertJson(['status' => 'error', 'message' => 'Invalid API token.']);
});

it('returns the plain short link for format=text', function () {
    $response = $this->get("/api?api={$this->owner->api_token}&url=https://example.com/a&format=text");

    $response->assertOk();
    expect($response->getContent())->toStartWith(url('/'));
});

it('rate limits to 60 calls per minute', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api?api=rate-limited&url=https://example.com/a')->assertOk();
    }

    $this->getJson('/api?api=rate-limited&url=https://example.com/a')->assertStatus(429);
});

test('array query parameters return a json error instead of a server error', function () {
    $this->getJson('/api?api[]=x&url[]=y&format=json')
        ->assertOk()
        ->assertJson(['status' => 'error']);
});
