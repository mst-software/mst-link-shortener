<?php

use App\Enums\InvalidReason;
use App\Enums\ViewStatus;
use App\Enums\VisitMode;
use App\Enums\VisitStatus;
use App\Http\Controllers\VisitController;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\View;
use App\Models\VisitSession;
use App\Services\Settings;
use App\Services\Views\FakeCaptchaVerifier;
use App\Services\Views\VisitFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const KEYWORD_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36';
const KEYWORD_FINGERPRINT = 'f1e2d3c4b5a69788796a5b4c3d2e1f00';
const KEYWORD_DESTINATION = 'https://example.com/keyword-destination';

beforeEach(function () {
    Carbon::setTestNow('2026-11-13 10:00:00');

    $this->link = Link::factory()->create(['alias' => 'Kw1abc', 'level' => 2, 'url' => KEYWORD_DESTINATION]);
    $this->campaign = Campaign::factory()->create(['keyword' => 'how to repot a houseplant', 'display_domain' => 'garden-exa***.com']);

    $this->withCredentials()->withHeader('User-Agent', KEYWORD_UA);
});

afterEach(function () {
    Carbon::setTestNow();
});

function openKeywordLink(): VisitSession
{
    test()->get('/Kw1abc')->assertRedirect('/go/Kw1abc');

    return VisitSession::query()->latest()->firstOrFail();
}

function keywordInfo(VisitSession $session)
{
    return test()
        ->withCookie(VisitController::COOKIE, $session->getKey())
        ->postJson(route('visit.info'), ['alias' => 'Kw1abc', 'fingerprint' => KEYWORD_FINGERPRINT]);
}

function submitCode(VisitSession $session, string $code, array $overrides = [])
{
    return test()
        ->withCookie(VisitController::COOKIE, $session->getKey())
        ->postJson(route('visit.check-captcha'), array_merge([
            'alias' => 'Kw1abc',
            'code' => $code,
            'fingerprint' => KEYWORD_FINGERPRINT,
            'captcha_response' => 'passed-captcha',
        ], $overrides));
}

function issueCode(Campaign $campaign, string $code = 'abc234', array $overrides = []): CodeAttempt
{
    return CodeAttempt::factory()->issued()->create(array_merge([
        'campaign_id' => $campaign->id,
        'code' => $code,
        'ua_hash' => hash('sha256', KEYWORD_UA),
        'ip' => '127.0.0.1',
    ], $overrides));
}

/**
 * Open the link and load the page data, as the browser does before the visitor types a code.
 */
function startKeywordSession(): VisitSession
{
    $session = openKeywordLink();
    keywordInfo($session)->assertOk();

    return $session->fresh();
}

/**
 * A second keyword session of the same browser, created directly because the test client keeps the first cookie.
 */
function anotherKeywordSession(): VisitSession
{
    return VisitSession::factory()->create([
        'mode' => VisitMode::Keyword,
        'campaign_id' => Campaign::query()->firstOrFail()->id,
        'ua_hash' => hash('sha256', KEYWORD_UA),
        'ip' => '127.0.0.1',
        'fingerprint' => KEYWORD_FINGERPRINT,
        'link_id' => Link::query()->firstOrFail()->id,
    ]);
}

it('starts a keyword session with the campaign and returns the keyword page data', function () {
    $session = openKeywordLink();

    expect($session->mode)->toBe(VisitMode::Keyword)
        ->and($session->campaign_id)->toBe($this->campaign->id);

    keywordInfo($session)
        ->assertOk()
        ->assertJson([
            'mode' => 'keyword',
            'keyword' => 'how to repot a houseplant',
            'display_domain' => 'garden-exa***.com',
            'images' => ['google' => null, 'button' => null, 'position' => null],
            'session_seconds_left' => 600,
            'tutorial_video_url' => '',
            'captcha_required' => true,
        ])
        ->assertJsonPath('token', null);

    expect($session->fresh()->fingerprint)->toBe(KEYWORD_FINGERPRINT);
});

it('counts the session time left down on the server clock', function () {
    $session = openKeywordLink();

    Carbon::setTestNow(now()->addSeconds(100));

    keywordInfo($session)->assertOk()->assertJsonPath('session_seconds_left', 500);
});

it('keeps the wait mode behaviour and adds the mode key when there is no campaign', function () {
    $this->campaign->delete();

    $session = openKeywordLink();

    expect($session->mode)->toBe(VisitMode::Wait)->and($session->campaign_id)->toBeNull();

    keywordInfo($session)
        ->assertOk()
        ->assertJson(['mode' => 'wait', 'step' => 1, 'total_steps' => 2, 'dwell_seconds' => 10, 'captcha_required' => false])
        ->assertJsonStructure(['token'])
        ->assertJsonMissingPath('keyword');
});

it('falls back to wait mode when visit mode is keyword but no campaign is available', function () {
    Settings::set('visit_mode', 'keyword');
    $this->campaign->update(['is_active' => false]);

    expect(openKeywordLink()->mode)->toBe(VisitMode::Wait);
});

it('uses wait mode when the admin forces it even though a campaign exists', function () {
    Settings::set('visit_mode', 'wait');

    expect(openKeywordLink()->mode)->toBe(VisitMode::Wait);
});

it('switches to wait mode on page load when the campaign is switched off and the device has no code', function () {
    $session = openKeywordLink();
    $this->campaign->update(['is_active' => false]);

    keywordInfo($session)->assertOk()->assertJson(['mode' => 'wait', 'step' => 1])->assertJsonStructure(['token']);

    expect($session->fresh()->mode)->toBe(VisitMode::Wait);
});

it('switches to wait mode when the campaign is over its daily limit and the device has no code', function () {
    $session = openKeywordLink();
    $this->campaign->update(['daily_limit' => 1]);
    issueCode($this->campaign, 'zzz999', ['ua_hash' => hash('sha256', 'another browser')]);

    keywordInfo($session)->assertOk()->assertJson(['mode' => 'wait']);
});

it('keeps keyword mode when the device already holds a code although the campaign is switched off', function () {
    $session = openKeywordLink();
    issueCode($this->campaign);
    $this->campaign->update(['is_active' => false]);

    keywordInfo($session)->assertOk()->assertJson(['mode' => 'keyword']);

    expect($session->fresh()->mode)->toBe(VisitMode::Keyword);
});

it('asks the page to reload when the campaign disappears at the code step', function () {
    $session = startKeywordSession();
    $this->campaign->update(['is_active' => false]);

    submitCode($session, 'abc234')->assertStatus(422)->assertJson(['error' => 'mode_changed']);

    expect($session->fresh()->mode)->toBe(VisitMode::Wait);
});

it('accepts a valid code, consumes it and records a pending view', function () {
    $session = startKeywordSession();
    $attempt = issueCode($this->campaign);

    submitCode($session, 'ABC234 ')->assertOk()->assertJson(['destination' => KEYWORD_DESTINATION]);

    $view = View::query()->sole();

    expect($view->status)->toBe(ViewStatus::Pending)
        ->and($view->invalid_reason)->toBeNull()
        ->and($attempt->fresh()->used_at)->not->toBeNull()
        ->and($attempt->fresh()->visit_session_id)->toBe($session->id)
        ->and($session->fresh()->status)->toBe(VisitStatus::Completed);
});

it('returns the same destination when the accepted code is submitted again', function () {
    $session = startKeywordSession();
    issueCode($this->campaign);

    submitCode($session, 'abc234')->assertOk();
    submitCode($session, 'abc234')->assertOk()->assertJson(['destination' => KEYWORD_DESTINATION]);

    expect(View::query()->count())->toBe(1);
});

it('lets only one of two sessions redeem the same code', function () {
    $first = startKeywordSession();
    issueCode($this->campaign);

    submitCode($first, 'abc234')->assertOk();

    $second = anotherKeywordSession();

    submitCode($second, 'abc234')->assertStatus(422)->assertJson(['error' => 'invalid_code', 'failures_left' => 4]);

    expect(View::query()->count())->toBe(1)
        ->and(CodeAttempt::query()->sole()->visit_session_id)->toBe($first->id);
});

it('does not consume a valid code when the captcha fails', function () {
    $session = startKeywordSession();
    $attempt = issueCode($this->campaign);

    submitCode($session, 'abc234', ['captcha_response' => FakeCaptchaVerifier::FAILING_RESPONSE])
        ->assertStatus(422)
        ->assertJson(['error' => 'captcha_failed']);

    expect($attempt->fresh()->used_at)->toBeNull()
        ->and($session->fresh()->code_failures)->toBe(0)
        ->and(View::query()->count())->toBe(0);

    submitCode($session, 'abc234')->assertOk();
});

it('does not accept a code of another campaign', function () {
    $session = startKeywordSession();
    $other = Campaign::factory()->create();
    $attempt = issueCode($other, 'oth234');

    submitCode($session, 'oth234')->assertStatus(422)->assertJson(['error' => 'invalid_code']);

    expect($attempt->fresh()->used_at)->toBeNull();
});

it('refuses a code issued to another device without consuming it', function () {
    $session = startKeywordSession();
    $attempt = issueCode($this->campaign, 'abc234', ['ua_hash' => hash('sha256', 'Mozilla/5.0 (iPhone) Safari/604.1')]);

    submitCode($session, 'abc234')
        ->assertStatus(422)
        ->assertJson(['error' => 'wrong_device', 'failures_left' => 4]);

    expect($attempt->fresh()->used_at)->toBeNull()
        ->and($attempt->fresh()->visit_session_id)->toBeNull()
        ->and($session->fresh()->code_failures)->toBe(1);
});

it('refuses an expired code', function () {
    $session = startKeywordSession();
    issueCode($this->campaign, 'abc234', ['expires_at' => now()->subSecond()]);

    submitCode($session, 'abc234')->assertStatus(422)->assertJson(['error' => 'expired_code']);
});

it('locks the session after five wrong codes', function () {
    $session = startKeywordSession();
    $attempt = issueCode($this->campaign);

    foreach ([4, 3, 2, 1] as $left) {
        submitCode($session, 'wrong1')->assertStatus(422)->assertJson(['error' => 'invalid_code', 'failures_left' => $left]);
    }

    submitCode($session, 'wrong1')->assertStatus(422)->assertJson(['error' => 'locked', 'failures_left' => 0]);

    // Even the right code is refused now.
    submitCode($session, 'abc234')->assertStatus(422)->assertJson(['error' => 'locked'])
        ->assertJsonFragment(['message' => 'Too many wrong codes. Please open the link again.']);

    expect($session->fresh()->status)->toBe(VisitStatus::Failed)
        ->and($attempt->fresh()->used_at)->toBeNull();
});

it('locks an IP after ten wrong codes in an hour on one campaign even across sessions', function () {
    foreach (range(1, 10) as $number) {
        submitCode(anotherKeywordSession(), 'wrong'.$number)->assertStatus(422);
    }

    $session = anotherKeywordSession();
    issueCode($this->campaign);

    submitCode($session, 'abc234')->assertStatus(422)->assertJson(['error' => 'locked'])
        ->assertJsonFragment(['message' => 'Too many wrong codes from your network. Please try again in about an hour.']);
});

it('still returns the destination but marks the view invalid when the code came from another IP', function () {
    $session = startKeywordSession();
    issueCode($this->campaign, 'abc234', ['ip' => '198.51.100.20']);

    submitCode($session, 'abc234')->assertOk()->assertJson(['destination' => KEYWORD_DESTINATION]);

    $view = View::query()->sole();

    expect($view->status)->toBe(ViewStatus::Invalid)
        ->and($view->invalid_reason)->toBe(InvalidReason::IpChanged)
        ->and(CodeAttempt::query()->sole()->used_at)->not->toBeNull();
});

it('refuses the code after the code session time is over', function () {
    $session = startKeywordSession();
    issueCode($this->campaign, 'abc234', ['expires_at' => now()->addHour()]);

    Carbon::setTestNow(now()->addSeconds(601));

    submitCode($session, 'abc234')->assertStatus(422)->assertJson(['error' => 'expired']);
});

it('records no javascript when the fingerprint of the final request is missing', function () {
    $session = startKeywordSession();
    issueCode($this->campaign);

    submitCode($session, 'abc234', ['fingerprint' => null])->assertOk();

    expect(View::query()->sole()->invalid_reason)->toBe(InvalidReason::NoJavascript);
});

it('answers no cookie at the code step when the visit cookie is missing', function () {
    openKeywordLink();

    $this->postJson(route('visit.check-captcha'), ['alias' => 'Kw1abc', 'code' => 'abc234', 'captcha_response' => 'ok'])
        ->assertStatus(422)
        ->assertJson(['error' => 'no_cookie']);
});

it('requires the step token in wait mode', function () {
    $this->campaign->delete();
    $session = openKeywordLink();

    submitCode($session, 'abc234')->assertStatus(422)->assertJson(['error' => 'bad_token']);
});

it('requires a passing captcha to report an error', function () {
    $this->postJson(route('visit.report'), ['alias' => 'Kw1abc', 'choice' => 'code_not_shown'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('captcha_response');

    $this->postJson(route('visit.report'), ['alias' => 'Kw1abc', 'choice' => 'code_not_shown', 'captcha_response' => FakeCaptchaVerifier::FAILING_RESPONSE])
        ->assertStatus(422)
        ->assertJson(['error' => 'captcha_failed']);

    expect(FraudFlag::query()->count())->toBe(0);
});

it('stores the choice and the campaign of the session in the report', function () {
    $session = startKeywordSession();

    $this->withCookie(VisitController::COOKIE, $session->getKey())
        ->postJson(route('visit.report'), ['alias' => 'Kw1abc', 'choice' => 'page_not_shown', 'captcha_response' => 'passed-captcha'])
        ->assertOk();

    $flag = FraudFlag::query()->sole();

    expect($flag->reason)->toBe('visitor_report')
        ->and($flag->details['choice'])->toBe('page_not_shown')
        ->and($flag->details['campaign_id'])->toBe($this->campaign->id);
});

it('starts a fresh session when the code window of the old keyword session is over', function () {
    $old = openKeywordLink();

    Carbon::setTestNow(now()->addSeconds(601));

    $this->withCookie(VisitController::COOKIE, $old->getKey())->get('/Kw1abc')->assertRedirect('/go/Kw1abc');

    $new = VisitSession::query()->latest('created_at')->latest('id')->firstOrFail();

    expect($new->getKey())->not->toBe($old->getKey())
        ->and($old->fresh()->status)->toBe(VisitStatus::Expired)
        ->and(VisitSession::query()->count())->toBe(2);
});

it('resumes the keyword session while the code window is still open', function () {
    $old = openKeywordLink();

    Carbon::setTestNow(now()->addSeconds(300));

    $this->withCookie(VisitController::COOKIE, $old->getKey())->get('/Kw1abc')->assertRedirect('/go/Kw1abc');

    expect(VisitSession::query()->count())->toBe(1)
        ->and($old->fresh()->status)->toBe(VisitStatus::InProgress);
});

it('uses one fallback for a non-positive code session length everywhere', function () {
    Settings::set('code_session_seconds', 0);

    expect(Settings::codeSessionSeconds())->toBe(600);

    $session = openKeywordLink();

    expect(app(VisitFlow::class)->sessionSecondsLeft($session))->toBe(600);
});

it('does not let a code of another IP keep the session in keyword mode', function () {
    $session = openKeywordLink();
    issueCode($this->campaign, 'abc234', ['ip' => '198.51.100.7']);
    $this->campaign->update(['is_active' => false]);

    keywordInfo($session)->assertOk()->assertJson(['mode' => 'wait']);
});

it('does not let a code issued before the session began keep it in keyword mode', function () {
    issueCode($this->campaign, 'abc234', ['issued_at' => now()->subMinute()]);
    $session = openKeywordLink();
    $this->campaign->update(['is_active' => false]);

    keywordInfo($session)->assertOk()->assertJson(['mode' => 'wait']);
});
