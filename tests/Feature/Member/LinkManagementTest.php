<?php

use App\Enums\LinkStatus;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.url' => 'https://linkcash.test']);
    $this->member = User::factory()->create();
});

it('creates a short link and returns it as json', function () {
    $this->actingAs($this->member)
        ->postJson('/links/shorten', ['url' => 'https://example.com/page', 'alias' => 'my-link', 'ad_type' => 2])
        ->assertOk()
        ->assertJson(['success' => true, 'data' => ['alias' => 'my-link']])
        ->assertJsonPath('data.short_url', url('my-link'));

    expect(Link::query()->where('alias', 'my-link')->first()->level)->toBe(2);
});

it('returns 422 json for invalid data', function () {
    $this->actingAs($this->member)->postJson('/links/shorten', ['url' => 'not a url'])
        ->assertStatus(422)->assertJson(['success' => false]);

    $this->actingAs($this->member)->postJson('/links/shorten', ['url' => 'https://example.com', 'ad_type' => 9])
        ->assertStatus(422)->assertJson(['success' => false]);
});

it('uses the member default level when ad_type is missing', function () {
    $member = User::factory()->create(['default_level' => 3]);

    $this->actingAs($member)->postJson('/links/shorten', ['url' => 'https://example.com/x'])->assertOk();

    expect(Link::query()->where('user_id', $member->id)->first()->level)->toBe(3);
});

it('lists only the member links and filters by alias', function () {
    Link::factory()->for($this->member)->create(['alias' => 'mine-one']);
    Link::factory()->for($this->member)->create(['alias' => 'other-two']);
    Link::factory()->create(['alias' => 'mine-three']);

    $this->actingAs($this->member)->get('/member/links')
        ->assertOk()->assertSee('mine-one')->assertSee('other-two')->assertDontSee('mine-three');

    $this->actingAs($this->member)->post('/member/links', ['filter' => ['alias' => 'mine']])
        ->assertOk()->assertSee('mine-one')->assertDontSee('other-two')->assertDontSee('mine-three');
});

it('hides and restores a link', function () {
    $link = Link::factory()->for($this->member)->create();

    $this->actingAs($this->member)->post('/member/links/hide', ['alias' => $link->alias])->assertRedirect();
    expect($link->fresh()->status)->toBe(LinkStatus::Hidden);

    $this->actingAs($this->member)->get('/member/links/hidden')->assertSee($link->alias);
    $this->actingAs($this->member)->get('/member/links')->assertDontSee($link->alias);

    $this->actingAs($this->member)->post('/member/links/restore', ['alias' => $link->alias])->assertRedirect();
    expect($link->fresh()->status)->toBe(LinkStatus::Active);
});

it('forbids hiding or restoring another member link', function () {
    $link = Link::factory()->create();

    $this->actingAs($this->member)->post('/member/links/hide', ['alias' => $link->alias])->assertForbidden();
    $this->actingAs($this->member)->post('/member/links/restore', ['alias' => $link->alias])->assertForbidden();
});

it('shows the info page to the owner only', function () {
    $link = Link::factory()->for($this->member)->create(['url' => 'https://example.com/target', 'views_count' => 42]);

    $this->actingAs($this->member)->get("/{$link->alias}/info")
        ->assertOk()->assertSee('https://example.com/target')->assertSee('42');

    $this->actingAs(User::factory()->create())->get("/{$link->alias}/info")->assertForbidden();

    auth()->logout();
    $this->get("/{$link->alias}/info")->assertRedirect(route('login'));
});
