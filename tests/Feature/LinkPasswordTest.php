<?php

use App\Http\Controllers\VisitController;
use App\Models\Link;
use App\Models\VisitSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->link = Link::factory()->passwordProtected('open-sesame')->create(['alias' => 'Locked1']);
});

it('asks for the password before starting a visit session', function () {
    $this->get('/Locked1')->assertRedirect('/go/Locked1');

    $this->get('/go/Locked1')
        ->assertOk()
        ->assertSee('This link is protected by a password')
        ->assertSee(route('visit.unlock', ['alias' => 'Locked1']), false)
        ->assertDontSee('id="visit"', false);

    expect(VisitSession::query()->count())->toBe(0);
});

it('requires a password to unlock', function () {
    $this->post('/go/Locked1/unlock', [])->assertSessionHasErrors('password');
});

it('rejects a wrong password and keeps the link locked', function () {
    $this->post('/go/Locked1/unlock', ['password' => 'wrong'])
        ->assertRedirect('/go/Locked1')
        ->assertSessionHasErrors(['password' => 'The password is incorrect.']);

    $this->get('/Locked1')->assertRedirect('/go/Locked1');

    expect(VisitSession::query()->count())->toBe(0);
});

it('starts the visit flow after the right password', function () {
    $this->post('/go/Locked1/unlock', ['password' => 'open-sesame'])->assertRedirect('/Locked1');

    $this->get('/Locked1')->assertRedirect('/go/Locked1')->assertCookie(VisitController::COOKIE);
    $this->get('/go/Locked1')->assertOk()->assertSee('id="visit"', false);

    expect(VisitSession::query()->sole()->link_id)->toBe($this->link->id);
});

it('stores each unlocked link once and nothing for an unprotected link', function () {
    Link::factory()->create(['alias' => 'Open1']);

    $this->post('/go/Locked1/unlock', ['password' => 'open-sesame']);
    $this->post('/go/Locked1/unlock', ['password' => 'open-sesame']);
    $this->post('/go/Open1/unlock', ['password' => 'anything'])->assertRedirect('/Open1');

    expect(session('visit.unlocked_links'))->toBe([$this->link->id]);
});

it('refuses the step endpoints until the link is unlocked', function () {
    $this->postJson(route('visit.info'), ['alias' => 'Locked1'])->assertForbidden();
    $this->postJson(route('visit.check-captcha'), ['alias' => 'Locked1', 'token' => 'x'])->assertForbidden();
});

it('answers 404 when unlocking an unknown or expired alias', function () {
    Link::factory()->passwordProtected()->expired()->create(['alias' => 'Expired1']);

    $this->post('/go/Unknown1/unlock', ['password' => 'secret-pass'])->assertNotFound();
    $this->post('/go/Expired1/unlock', ['password' => 'secret-pass'])->assertNotFound();
});

it('limits password attempts to 5 per minute per link and ip', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post('/go/Locked1/unlock', ['password' => 'wrong'])->assertSessionHasErrors('password');
    }

    $this->post('/go/Locked1/unlock', ['password' => 'open-sesame'])->assertStatus(429);
});

it('shares one password attempt limit across case variants of the alias', function () {
    foreach (['Locked1', 'locked1', 'LOCKED1', 'lOcKeD1', 'Locked1'] as $alias) {
        $this->post("/go/{$alias}/unlock", ['password' => 'wrong']);
    }

    $this->post('/go/locked1/unlock', ['password' => 'open-sesame'])->assertStatus(429);
});

it('never exposes the password hash when the link is serialized', function () {
    expect($this->link->toArray())->not->toHaveKey('password');
});
