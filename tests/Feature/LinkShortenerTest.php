<?php

use App\Enums\LinkStatus;
use App\Exceptions\InvalidLinkException;
use App\Models\Link;
use App\Models\User;
use App\Services\LinkShortener;
use App\Services\Settings;
use App\Support\Alias;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.url' => 'https://linkcash.test']);
    $this->user = User::factory()->create();
    $this->shortener = new LinkShortener;
});

it('rejects every reserved alias', function (string $alias) {
    $this->shortener->create($this->user, 'https://example.com', $alias);
})->with(Alias::RESERVED)->throws(InvalidLinkException::class);

it('stores Abc and abc as two different aliases', function () {
    $first = $this->shortener->create($this->user, 'https://example.com/1', 'Abc');
    $second = $this->shortener->create($this->user, 'https://example.com/2', 'abc');

    expect($first->alias)->toBe('Abc')
        ->and($second->alias)->toBe('abc')
        ->and(Link::query()->count())->toBe(2);
});

it('rejects a taken alias with a readable message', function () {
    $this->shortener->create($this->user, 'https://example.com/1', 'taken1');

    $this->shortener->create($this->user, 'https://example.com/2', 'taken1');
})->throws(InvalidLinkException::class, 'already taken');

it('rejects aliases with a bad shape', function (string $alias) {
    $this->shortener->create($this->user, 'https://example.com', $alias);
})->with([
    'two characters' => 'ab',
    'thirty-three characters' => str_repeat('a', 33),
    'space' => 'ab cd',
])->throws(InvalidLinkException::class);

it('generates a six character alias by default', function () {
    $link = $this->shortener->create($this->user, 'https://example.com', null);

    expect($link->alias)->toMatch('/\A[A-Za-z0-9]{6}\z/')
        ->and($link->status)->toBe(LinkStatus::Active)
        ->and($link->views_count)->toBe(0);
});

it('retries with a different alias when a generated alias already exists', function () {
    Settings::set('alias_length', 1);
    $this->shortener->create($this->user, 'https://example.com/0', 'zzz');

    // Occupy most single character aliases so that retries are exercised.
    foreach (array_slice(str_split(Alias::ALPHABET), 0, 40) as $char) {
        Link::factory()->create(['user_id' => $this->user->id, 'alias' => $char]);
    }

    $link = $this->shortener->create($this->user, 'https://example.com', null);

    expect($link->alias)->not->toBe('zzz')
        ->and(Link::query()->count())->toBe(42);
});

it('increases the alias length when every attempt at the current length collides', function () {
    Settings::set('alias_length', 1);

    foreach (str_split(Alias::ALPHABET) as $char) {
        Link::factory()->create(['user_id' => $this->user->id, 'alias' => $char]);
    }

    $link = $this->shortener->create($this->user, 'https://example.com', null);

    expect($link->alias)->toHaveLength(2);
});

it('uses the member default level when none is given', function () {
    $user = User::factory()->create(['default_level' => 3]);

    $link = $this->shortener->create($user, 'https://example.com', null);

    expect($link->level)->toBe(3);
});

it('uses an explicit level over the default', function () {
    $link = $this->shortener->create($this->user, 'https://example.com', null, 2);

    expect($link->level)->toBe(2);
});

it('rejects levels outside 1 to 3', function (int $level) {
    $this->shortener->create($this->user, 'https://example.com', null, $level);
})->with([0, 4])->throws(InvalidLinkException::class);

it('rejects unsafe or unsupported urls', function (string $url) {
    $this->shortener->create($this->user, $url, null);
})->with([
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html,x',
    'ftp scheme' => 'ftp://x',
    'the app host' => 'https://linkcash.test/some/path',
    'too long' => 'https://example.com/'.str_repeat('a', 2030),
])->throws(InvalidLinkException::class);

it('stores a valid url unchanged', function () {
    $link = $this->shortener->create($this->user, 'https://example.com/a?b=c', null);

    expect($link->fresh()->url)->toBe('https://example.com/a?b=c');
});

it('belongs to its owner', function () {
    $link = $this->shortener->create($this->user, 'https://example.com', null);

    expect($link->user->is($this->user))->toBeTrue();
});
