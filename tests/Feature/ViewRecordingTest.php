<?php

use App\Enums\InvalidReason;
use App\Enums\ViewStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\VisitController;
use App\Models\BlockedNetwork;
use App\Models\CpmRate;
use App\Models\Link;
use App\Models\View;
use App\Models\ViewDedup;
use App\Models\VisitSession;
use App\Services\Views\FakeCaptchaVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const RECORDING_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36';
const RECORDING_FINGERPRINT = '0f1e2d3c4b5a69788796a5b4c3d2e1f0';
const RECORDING_DESTINATION = 'https://example.com/destination';

beforeEach(function () {
    Carbon::setTestNow('2026-11-13 10:00:00');

    CpmRate::factory()->create(['country' => 'VN', 'level' => 3, 'rate_per_1000_micro' => 7_500_000]);
    CpmRate::factory()->create(['country' => 'XX', 'level' => 3, 'rate_per_1000_micro' => 1_000_000]);

    $this->link = Link::factory()->create(['alias' => 'Rec3ab', 'level' => 3, 'url' => RECORDING_DESTINATION]);

    $this->withCredentials()->withHeaders(['User-Agent' => RECORDING_UA, 'CF-IPCountry' => 'VN']);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Open the link and pass the given number of steps, waiting the dwell time after each one.
 *
 * @return array{0: VisitSession, 1: string} The session and the token of the last step reached.
 */
function walkSteps(int $steps = 3, bool $waitAfterLast = true): array
{
    test()->get('/Rec3ab')->assertRedirect('/go/Rec3ab');

    return passSteps(VisitSession::query()->latest()->firstOrFail(), $steps, $waitAfterLast);
}

/**
 * Pass the given number of steps of an opened session, waiting the dwell time after each one.
 *
 * @return array{0: VisitSession, 1: string}
 */
function passSteps(VisitSession $session, int $steps = 3, bool $waitAfterLast = true): array
{
    test()->withCookie(VisitController::COOKIE, $session->getKey());
    $token = null;

    foreach (range(1, $steps) as $step) {
        if ($token !== null) {
            Carbon::setTestNow(now()->addSeconds(10));
        }

        $token = test()->postJson(route('visit.info'), array_filter([
            'alias' => 'Rec3ab',
            'fingerprint' => RECORDING_FINGERPRINT,
            'token' => $token,
        ]))->assertOk()->assertJson(['step' => $step])->json('token');
    }

    if ($waitAfterLast) {
        Carbon::setTestNow(now()->addSeconds(10));
    }

    return [$session, $token];
}

function finishVisit(string $token, array $overrides = [])
{
    return test()->postJson(route('visit.check-captcha'), array_merge([
        'alias' => 'Rec3ab',
        'token' => $token,
        'fingerprint' => RECORDING_FINGERPRINT,
        'captcha_response' => 'passed-captcha',
    ], $overrides));
}

function expectInvalidView(InvalidReason $reason): void
{
    $view = View::query()->sole();

    expect($view->status)->toBe(ViewStatus::Invalid)
        ->and($view->invalid_reason)->toBe($reason)
        ->and($view->earn_micro)->toBe(0)
        ->and(Link::query()->sole()->views_count)->toBe(0)
        ->and(ViewDedup::query()->count())->toBe(0);
}

it('records a clean three step visit as a pending view at the country rate', function () {
    [$session, $token] = walkSteps();

    finishVisit($token)->assertOk()->assertExactJson(['destination' => RECORDING_DESTINATION]);

    $view = View::query()->sole();

    expect($view->status)->toBe(ViewStatus::Pending)
        ->and($view->invalid_reason)->toBeNull()
        ->and($view->country)->toBe('VN')
        ->and($view->level)->toBe(3)
        ->and($view->earn_micro)->toBe(7_500)
        ->and($view->user_id)->toBe($this->link->user_id)
        ->and($view->visit_session_id)->toBe($session->getKey())
        ->and($view->matures_at->equalTo(now()->addDays(3)))->toBeTrue()
        ->and($this->link->fresh()->views_count)->toBe(1)
        ->and(ViewDedup::query()->sole()->expires_at->equalTo(now()->addHours(24)))->toBeTrue()
        ->and($session->fresh()->status)->toBe(VisitStatus::Completed);
});

it('does not pay a second view from the same IP and fingerprint within the unique window', function () {
    [, $token] = walkSteps();
    finishVisit($token)->assertOk();

    Carbon::setTestNow(now()->addHours(1));
    [, $token] = walkSteps();
    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    $second = View::query()->latest('id')->first();

    expect($second->status)->toBe(ViewStatus::Invalid)
        ->and($second->invalid_reason)->toBe(InvalidReason::Duplicate)
        ->and($second->earn_micro)->toBe(0)
        ->and($this->link->fresh()->views_count)->toBe(1);
});

it('pays again after the unique window has passed', function () {
    [, $token] = walkSteps();
    finishVisit($token)->assertOk();

    Carbon::setTestNow(now()->addHours(25));
    [, $token] = walkSteps();
    finishVisit($token)->assertOk();

    $second = View::query()->latest('id')->first();

    expect($second->status)->toBe(ViewStatus::Pending)
        ->and($second->earn_micro)->toBe(7_500)
        ->and($this->link->fresh()->views_count)->toBe(2)
        ->and(ViewDedup::query()->count())->toBe(1);
});

it('marks a last step finished before the dwell time as too short', function () {
    [, $token] = walkSteps(waitAfterLast: false);

    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    expectInvalidView(InvalidReason::DwellTooShort);
});

it('marks a failed captcha', function () {
    [, $token] = walkSteps();

    finishVisit($token, ['captcha_response' => FakeCaptchaVerifier::FAILING_RESPONSE])
        ->assertOk()
        ->assertJson(['destination' => RECORDING_DESTINATION]);

    expectInvalidView(InvalidReason::CaptchaFailed);
});

it('marks a missing captcha as failed', function () {
    [, $token] = walkSteps();

    finishVisit($token, ['captcha_response' => null])->assertOk();

    expectInvalidView(InvalidReason::CaptchaFailed);
});

it('marks a bot user agent', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)');
    [, $token] = walkSteps();

    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    expectInvalidView(InvalidReason::Bot);
});

it('marks a visit from a blocked network', function () {
    BlockedNetwork::factory()->create(['cidr' => '127.0.0.0/8']);
    [, $token] = walkSteps();

    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    expectInvalidView(InvalidReason::BlockedNetwork);
});

it('marks a visit whose IP changed during the flow', function () {
    [, $token] = walkSteps();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20']);

    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    expectInvalidView(InvalidReason::IpChanged);
});

it('marks a visit whose IP changed in the middle and came back', function () {
    test()->get('/Rec3ab');
    $session = VisitSession::query()->sole();
    $session->forceFill(['ip_changed' => true])->save();
    [, $token] = passSteps($session);

    finishVisit($token)->assertOk();

    expectInvalidView(InvalidReason::IpChanged);
});

it('marks a last request without the browser fingerprint as no javascript', function () {
    [, $token] = walkSteps();

    finishVisit($token, ['fingerprint' => null])->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    expectInvalidView(InvalidReason::NoJavascript);
});

it('marks a last request without the visit cookie as no cookie', function () {
    [, $token] = walkSteps();
    $this->defaultCookies = [];

    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    expectInvalidView(InvalidReason::NoCookie);
});

it('marks a visit that skipped steps', function () {
    [, $token] = walkSteps(steps: 1);

    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    expectInvalidView(InvalidReason::MissingSteps);
});

it('refuses a tampered token without recording a view', function () {
    [, $token] = walkSteps();

    finishVisit($token.'x')->assertStatus(422);

    expect(View::query()->count())->toBe(0);
});

it('records exactly one view and one dedup row when the last step is sent twice', function () {
    [, $token] = walkSteps();

    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);
    finishVisit($token)->assertOk()->assertJson(['destination' => RECORDING_DESTINATION]);

    expect(View::query()->count())->toBe(1)
        ->and(ViewDedup::query()->count())->toBe(1)
        ->and($this->link->fresh()->views_count)->toBe(1);
});

it('keeps the earned amount when the rate changes later', function () {
    [, $token] = walkSteps();
    finishVisit($token)->assertOk();

    CpmRate::query()->where('country', 'VN')->where('level', 3)->update(['rate_per_1000_micro' => 20_000_000]);

    expect(View::query()->sole()->earn_micro)->toBe(7_500);
});
