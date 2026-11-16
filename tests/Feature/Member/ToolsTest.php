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

it('regenerates the api token and the old token stops working', function () {
    $oldToken = $this->member->api_token;

    $this->actingAs($this->member)
        ->post(route('member.tools.api.regenerate-token'), ['current_password' => 'password'])
        ->assertRedirect(route('member.tools.api'))
        ->assertSessionHas('success');

    $newToken = $this->member->refresh()->api_token;

    expect($newToken)->not->toBe($oldToken);
    $this->get(route('member.tools.api'))->assertSee($newToken)->assertDontSee($oldToken);
    $this->get("/st?api={$oldToken}&url=https://example.com/q")->assertStatus(422);
    $this->getJson("/api?api={$oldToken}&url=https://example.com/a")->assertJson(['status' => 'error']);
    $this->get("/st?api={$newToken}&url=https://example.com/q")->assertOk();
});

it('needs the current password to regenerate the api token', function (?string $currentPassword) {
    $oldToken = $this->member->api_token;

    $this->actingAs($this->member)
        ->post(route('member.tools.api.regenerate-token'), ['current_password' => $currentPassword])
        ->assertSessionHasErrors('current_password');

    expect($this->member->refresh()->api_token)->toBe($oldToken);
})->with(['wrong password' => 'wrong', 'missing' => null]);

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

it('ignores password and expiry parameters on the quick link', function () {
    $this->get("/st?api={$this->member->api_token}&url=https://example.com/q&password=open-sesame&expires_at=2020-01-01")
        ->assertOk();

    $link = Link::query()->where('user_id', $this->member->id)->firstOrFail();

    expect($link->password)->toBeNull()
        ->and($link->expires_at)->toBeNull();
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
