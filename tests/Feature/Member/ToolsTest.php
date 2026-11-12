<?php

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.url' => 'https://linkcash.test']);
    $this->member = User::factory()->create(['default_level' => 3]);
});

it('shows the member token on every tool page', function (string $path) {
    $this->actingAs($this->member)->get($path)->assertOk()->assertSee($this->member->api_token);
})->with(['/member/tools/quick', '/member/tools/full', '/member/tools/api']);

it('shows the full page script variables', function () {
    $this->actingAs($this->member)->get('/member/tools/full')
        ->assertSee('linkcash_url')->assertSee('linkcash_api_token')
        ->assertSee('linkcash_advert')->assertSee('linkcash_domains');
});

it('creates a link for the token owner through quick link with the default level', function () {
    $response = $this->get("/st?api={$this->member->api_token}&url=https://example.com/q");

    $response->assertOk();
    $link = Link::query()->where('user_id', $this->member->id)->firstOrFail();

    expect($link->level)->toBe(3)
        ->and($response->getContent())->toBe(url($link->alias));
});

it('mass shrinks every line with the default level', function () {
    $this->actingAs($this->member)
        ->post('/member/tools/mass-shrinker', ['urls' => "https://example.com/1\nhttps://example.com/2"])
        ->assertOk();

    expect(Link::query()->where('user_id', $this->member->id)->pluck('level')->all())->toBe([3, 3]);
});

it('reports a bad line without stopping the batch', function () {
    $this->actingAs($this->member)
        ->post('/member/tools/mass-shrinker', ['urls' => "https://example.com/1\nnot a url\nhttps://example.com/3"])
        ->assertOk()
        ->assertSee('The URL must start with http:// or https://.');

    expect(Link::query()->count())->toBe(2);
});

it('rejects more than 20 lines', function () {
    $urls = collect(range(1, 21))->map(fn (int $i) => "https://example.com/{$i}")->implode("\n");

    $this->actingAs($this->member)
        ->post('/member/tools/mass-shrinker', ['urls' => $urls])
        ->assertSessionHasErrors('urls');

    expect(Link::query()->count())->toBe(0);
});
