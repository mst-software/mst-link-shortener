<?php

use App\Http\Controllers\VisitController;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\VisitSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const VISIT_FLOW_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36';
const VISIT_FLOW_FINGERPRINT = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

beforeEach(function () {
    Carbon::setTestNow('2026-11-13 10:00:00');
    $this->link = Link::factory()->create(['alias' => 'Ab3xYz', 'level' => 2]);
    $this->withHeader('User-Agent', VISIT_FLOW_UA);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Open the short link and return the visit session it created.
 */
function openShortLink(string $alias = 'Ab3xYz'): VisitSession
{
    test()->get('/'.$alias)->assertRedirect('/go/'.$alias);

    return VisitSession::query()->latest()->firstOrFail();
}

function stepInfoRequest(VisitSession $session, array $data = [])
{
    // JSON test requests only carry cookies with credentials enabled.
    return test()
        ->withCredentials()
        ->withCookie(VisitController::COOKIE, $session->getKey())
        ->postJson(route('visit.info'), array_merge(['alias' => 'Ab3xYz', 'fingerprint' => VISIT_FLOW_FINGERPRINT], $data));
}

it('returns 404 for unknown, hidden and banned aliases', function () {
    Link::factory()->hidden()->create(['alias' => 'Hidden1']);
    Link::factory()->banned()->create(['alias' => 'Banned1']);

    $this->get('/Unknown1')->assertNotFound();
    $this->get('/Hidden1')->assertNotFound();
    $this->get('/Banned1')->assertNotFound();
    $this->get('/go/Unknown1')->assertNotFound();
});

it('redirects a short link to the visit page with a session cookie and no caching', function () {
    $response = $this->get('/Ab3xYz');

    $response->assertStatus(302)
        ->assertHeader('Location', url('/go/Ab3xYz'))
        ->assertCookie(VisitController::COOKIE);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    $session = VisitSession::query()->sole();

    expect($session->link_id)->toBe($this->link->id)
        ->and($session->level)->toBe(2)
        ->and($session->current_step)->toBe(1)
        ->and($session->ua_hash)->toBe(hash('sha256', VISIT_FLOW_UA))
        ->and($session->expires_at->equalTo(now()->addMinutes(30)))->toBeTrue();
});

it('reuses the session when the visitor opens the link again', function () {
    $session = openShortLink();

    $this->withCookie(VisitController::COOKIE, $session->getKey())->get('/Ab3xYz')->assertRedirect('/go/Ab3xYz');

    expect(VisitSession::query()->count())->toBe(1);
});

it('renders the single visit page with the untitled alias title', function () {
    $this->get('/go/Ab3xYz')
        ->assertOk()
        ->assertSee('<title>Untitled #Ab3xYz</title>', false)
        ->assertSee('Report an error')
        ->assertSee('Ready to earn money?');
});

it('issues the step 1 token on the first call', function () {
    $session = openShortLink();

    stepInfoRequest($session)
        ->assertOk()
        ->assertJson(['step' => 1, 'total_steps' => 2, 'dwell_seconds' => 10, 'captcha_required' => false])
        ->assertJsonStructure(['token']);

    expect($session->fresh()->fingerprint)->toBe(VISIT_FLOW_FINGERPRINT);
});

it('rejects the next step before the dwell time has passed', function () {
    $session = openShortLink();
    $token = stepInfoRequest($session)->json('token');

    Carbon::setTestNow(now()->addSeconds(5));

    stepInfoRequest($session, ['token' => $token])
        ->assertStatus(422)
        ->assertJson(['error' => 'too_early']);

    expect($session->fresh()->current_step)->toBe(1);
});

it('issues the next step token once the dwell time has passed', function () {
    $session = openShortLink();
    $token = stepInfoRequest($session)->json('token');

    Carbon::setTestNow(now()->addSeconds(10));

    $response = stepInfoRequest($session, ['token' => $token])
        ->assertOk()
        ->assertJson(['step' => 2, 'total_steps' => 2, 'captcha_required' => true]);

    expect($response->json('token'))->not->toBe($token)
        ->and($session->fresh()->current_step)->toBe(2);
});

it('rejects a reused step token', function () {
    Link::factory()->create(['alias' => 'Lvl3ab', 'level' => 3]);
    $session = openShortLink('Lvl3ab');
    $token = stepInfoRequest($session, ['alias' => 'Lvl3ab'])->json('token');

    Carbon::setTestNow(now()->addSeconds(10));
    stepInfoRequest($session, ['alias' => 'Lvl3ab', 'token' => $token])->assertOk();

    Carbon::setTestNow(now()->addSeconds(10));
    stepInfoRequest($session, ['alias' => 'Lvl3ab', 'token' => $token])
        ->assertStatus(422)
        ->assertJson(['error' => 'bad_token']);
});

it('answers no cookie when the visit cookie is missing', function () {
    openShortLink();

    $this->postJson(route('visit.info'), ['alias' => 'Ab3xYz', 'fingerprint' => VISIT_FLOW_FINGERPRINT])
        ->assertStatus(422)
        ->assertJson(['error' => 'no_cookie']);
});

it('answers no fingerprint when the fingerprint is missing', function () {
    $session = openShortLink();

    stepInfoRequest($session, ['fingerprint' => null])
        ->assertStatus(422)
        ->assertJson(['error' => 'no_fingerprint']);

    expect($session->fresh()->token_hash)->toBeNull();
});

it('does not give a step 2 token to a session that sends no fingerprint', function () {
    $session = openShortLink();
    $token = stepInfoRequest($session)->json('token');

    Carbon::setTestNow(now()->addSeconds(10));

    stepInfoRequest($session, ['token' => $token, 'fingerprint' => null])
        ->assertStatus(422)
        ->assertJson(['error' => 'no_fingerprint']);

    expect($session->fresh()->current_step)->toBe(1);
});

it('rejects a fingerprint that changes during the session', function () {
    $session = openShortLink();
    $token = stepInfoRequest($session)->json('token');

    Carbon::setTestNow(now()->addSeconds(10));

    stepInfoRequest($session, ['token' => $token, 'fingerprint' => 'ffffffffffffffffffffffffffffffff'])
        ->assertStatus(422)
        ->assertJson(['error' => 'bad_token']);
});

it('answers expired after the session lifetime', function () {
    $session = openShortLink();
    $token = stepInfoRequest($session)->json('token');

    Carbon::setTestNow(now()->addMinutes(31));

    stepInfoRequest($session, ['token' => $token])
        ->assertStatus(422)
        ->assertJson(['error' => 'expired']);
});

it('rejects a different user agent', function () {
    $session = openShortLink();
    $token = stepInfoRequest($session)->json('token');

    Carbon::setTestNow(now()->addSeconds(10));

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Safari/604.1');

    stepInfoRequest($session, ['token' => $token])
        ->assertStatus(422)
        ->assertJson(['error' => 'bad_token']);

    expect($session->fresh()->current_step)->toBe(1);
});

it('accepts a different IP and remembers it for the final check', function () {
    $session = openShortLink();
    $token = stepInfoRequest($session)->json('token');

    Carbon::setTestNow(now()->addSeconds(10));

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77']);

    stepInfoRequest($session, ['token' => $token])->assertOk()->assertJson(['step' => 2]);

    expect($session->fresh()->ip_changed)->toBeTrue();
});

it('stores a visitor report as a fraud flag', function () {
    $this->postJson(route('visit.report'), [
        'alias' => 'Ab3xYz',
        'reason' => 'code_not_shown',
        'captcha_response' => 'ok',
        'detail' => 'The code never appeared.',
    ])->assertOk();

    $flag = FraudFlag::query()->sole();

    expect($flag->reason)->toBe('visitor_report')
        ->and($flag->link_id)->toBe($this->link->id)
        ->and($flag->user_id)->toBe($this->link->user_id)
        ->and($flag->details['reason'])->toBe('code_not_shown')
        ->and($flag->details['detail'])->toBe('The code never appeared.');
});

it('rejects an unknown report reason', function () {
    $this->postJson(route('visit.report'), ['alias' => 'Ab3xYz', 'reason' => 'spam', 'captcha_response' => 'ok'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect(FraudFlag::query()->count())->toBe(0);
});

it('rate limits reports to five per minute', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('visit.report'), ['alias' => 'Ab3xYz', 'reason' => 'other', 'captcha_response' => 'ok'])->assertOk();
    }

    $this->postJson(route('visit.report'), ['alias' => 'Ab3xYz', 'reason' => 'other', 'captcha_response' => 'ok'])->assertStatus(429);

    expect(FraudFlag::query()->count())->toBe(5);
});
