<?php

namespace App\Http\Controllers;

use App\Contracts\CountryResolver;
use App\Enums\CaptchaProvider;
use App\Http\Requests\Visit\CheckCaptchaRequest;
use App\Http\Requests\Visit\ReportLinkRequest;
use App\Http\Requests\Visit\StepInfoRequest;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Services\Views\StepInfo;
use App\Services\Views\StepResult;
use App\Services\Views\StepTokenService;
use App\Services\Views\VisitFlow;
use App\Services\Views\VisitorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

class VisitController extends Controller
{
    /**
     * Cookie holding the visit session id (httpOnly, encrypted by the web middleware).
     */
    public const COOKIE = 'lc_visit';

    private const COOKIE_MINUTES = 60 * 24 * 30;

    public function __construct(
        private VisitFlow $flow,
        private StepTokenService $tokens,
        private CountryResolver $countries,
    ) {}

    /**
     * Open a short link: create or resume the visit session and send the visitor to the visit page.
     */
    public function start(Request $request, string $alias): RedirectResponse
    {
        $link = $this->activeLinkOrFail($alias);
        $context = $this->context($request);

        $session = $this->flow->findSession($link, $this->cookieValue($request));

        if ($session === null || ! $this->flow->isResumable($session, $context)) {
            $session = $this->flow->start($link, $context);
        }

        return redirect()
            ->route('visit.show', ['alias' => $link->alias])
            ->withCookie(Cookie::make(self::COOKIE, $session->getKey(), self::COOKIE_MINUTES, httpOnly: true))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * The single visitor page. Every step runs on it through AJAX, without reloading.
     */
    public function show(string $alias): Response
    {
        $link = $this->activeLinkOrFail($alias);
        $captcha = CaptchaProvider::current();

        return response()
            ->view('visit.go', [
                'alias' => $link->alias,
                'captchaProvider' => $captcha,
            ])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Issue the token of the current step, or move to the next step when the dwell time has passed.
     */
    public function info(StepInfoRequest $request): JsonResponse
    {
        $link = $this->activeLinkOrFail($request->string('alias')->toString());
        $sessionId = $this->cookieValue($request);

        if ($sessionId === null) {
            return $this->stepResponse(StepInfo::rejected(StepResult::NoCookie));
        }

        $session = $this->flow->findSession($link, $sessionId);

        if ($session === null) {
            return $this->stepResponse(StepInfo::rejected(StepResult::Expired));
        }

        return $this->stepResponse($this->flow->stepInfo(
            $session,
            $request->filled('token') ? $request->string('token')->toString() : null,
            $request->filled('fingerprint') ? $request->string('fingerprint')->toString() : null,
            $this->context($request),
        ));
    }

    /**
     * The last step: verify the captcha, run every rule and record the view. The destination is
     * returned even when the view is invalid; only a request without a valid step token is refused.
     */
    public function checkCaptcha(CheckCaptchaRequest $request): JsonResponse
    {
        $link = $this->activeLinkOrFail($request->string('alias')->toString());
        $token = $request->string('token')->toString();

        // The signed token names its session, so a missing cookie is recorded as a rule failure instead of a dead end.
        $session = $this->flow->findSession($link, $this->tokens->sessionId($token));

        if ($session === null) {
            return $this->errorResponse(StepResult::Expired);
        }

        $result = $this->flow->finish(
            $session,
            $token,
            $request->filled('fingerprint') ? $request->string('fingerprint')->toString() : null,
            $request->filled('captcha_response') ? $request->string('captcha_response')->toString() : null,
            $this->cookieValue($request) === $session->getKey(),
            $this->context($request),
        );

        if (! $result->isFinished()) {
            return $this->errorResponse($result->error ?? StepResult::BadToken);
        }

        return response()->json(['destination' => $link->url]);
    }

    /**
     * Store a visitor report from the "Report an error" dialog for the admin to review.
     */
    public function report(ReportLinkRequest $request): JsonResponse
    {
        $link = $this->activeLinkOrFail($request->string('alias')->toString());

        FraudFlag::query()->create([
            'user_id' => $link->user_id,
            'link_id' => $link->getKey(),
            'reason' => 'visitor_report',
            'details' => [
                'reason' => $request->string('reason')->toString(),
                'detail' => $request->filled('detail') ? $request->string('detail')->toString() : null,
                'ip' => $this->context($request)->ip,
            ],
        ]);

        return response()->json(['message' => 'Thank you. Your report has been sent.']);
    }

    private function stepResponse(StepInfo $info): JsonResponse
    {
        if (! $info->accepted()) {
            return $this->errorResponse($info->result);
        }

        return response()->json([
            'step' => $info->step,
            'total_steps' => $info->totalSteps,
            'dwell_seconds' => $info->dwellSeconds,
            'token' => $info->token,
            'captcha_required' => $info->captchaRequired,
        ]);
    }

    private function errorResponse(StepResult $result): JsonResponse
    {
        return response()->json([
            'error' => $result->value,
            'message' => $result->label(),
        ], 422);
    }

    private function activeLinkOrFail(string $alias): Link
    {
        $link = $this->flow->findActiveLink($alias);

        abort_if($link === null, 404);

        return $link;
    }

    private function cookieValue(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function context(Request $request): VisitorContext
    {
        return VisitorContext::fromRequest($request, $this->countries);
    }
}
