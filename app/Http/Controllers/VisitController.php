<?php

namespace App\Http\Controllers;

use App\Contracts\CaptchaVerifier;
use App\Contracts\CountryResolver;
use App\Enums\CaptchaProvider;
use App\Enums\VisitMode;
use App\Http\Requests\Visit\CheckCaptchaRequest;
use App\Http\Requests\Visit\ReportLinkRequest;
use App\Http\Requests\Visit\StepInfoRequest;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\VisitSession;
use App\Services\Views\StepInfo;
use App\Services\Views\StepResult;
use App\Services\Views\StepTokenService;
use App\Services\Views\VisitFlow;
use App\Services\Views\VisitorContext;
use App\Services\Visits\CodeRedeemer;
use App\Services\Visits\CodeResult;
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

    /**
     * Layout states the local preview can show; the first one of each mode is the default.
     */
    private const PREVIEW_STATES = [
        'keyword' => ['idle', 'typed', 'ready', 'error', 'low', 'expired', 'locked', 'locked-hour', 'report'],
        'wait' => ['counting', 'done', 'final'],
    ];

    public function __construct(
        private VisitFlow $flow,
        private StepTokenService $tokens,
        private CountryResolver $countries,
        private CaptchaVerifier $captcha,
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
    public function show(Request $request, string $alias): Response
    {
        $link = $this->activeLinkOrFail($alias);
        $captcha = CaptchaProvider::current();

        return response()
            ->view('visit.go', [
                'alias' => $link->alias,
                'captchaProvider' => $captcha,
                'preview' => $this->preview($request),
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
        $fingerprint = $request->filled('fingerprint') ? $request->string('fingerprint')->toString() : null;
        $captchaResponse = $request->filled('captcha_response') ? $request->string('captcha_response')->toString() : null;

        // The signed token names its session, so a missing cookie is recorded as a rule failure instead of a dead end.
        // A keyword session has no token and is found by its cookie.
        $session = $token !== ''
            ? $this->flow->findSession($link, $this->tokens->sessionId($token))
            : $this->flow->findSession($link, $this->cookieValue($request));

        if ($session === null) {
            return $this->errorResponse($token !== '' ? StepResult::Expired : StepResult::NoCookie);
        }

        if ($session->mode === VisitMode::Keyword) {
            return $this->checkCode($request, $link, $session, $fingerprint, $captchaResponse);
        }

        // A wait mode session is finished with the token of its last step only.
        if ($token === '') {
            return $this->errorResponse(StepResult::BadToken);
        }

        $result = $this->flow->finish(
            $session,
            $token,
            $fingerprint,
            $captchaResponse,
            $this->cookieValue($request) === $session->getKey(),
            $this->context($request),
        );

        if (! $result->isFinished()) {
            return $this->errorResponse($result->error ?? StepResult::BadToken);
        }

        return response()->json(['destination' => $link->url]);
    }

    /**
     * The code step of a keyword session. A refused code answers 422 with the remaining failures;
     * an accepted code always returns the destination, even when the view turns out invalid.
     */
    private function checkCode(
        CheckCaptchaRequest $request,
        Link $link,
        VisitSession $session,
        ?string $fingerprint,
        ?string $captchaResponse,
    ): JsonResponse {
        $result = $this->flow->finishKeyword(
            $session,
            $request->string('code')->toString(),
            $fingerprint,
            $captchaResponse,
            $this->cookieValue($request) === $session->getKey(),
            $this->context($request),
        );

        if ($result->codeError !== null) {
            return $this->codeErrorResponse($result->codeError, $session);
        }

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

        $context = $this->context($request);

        if (! $this->captcha->verify($request->string('captcha_response')->toString(), $context->ip)) {
            return $this->errorResponse(StepResult::CaptchaFailed);
        }

        $choice = $request->filled('choice') ? $request->string('choice')->toString() : $request->string('reason')->toString();
        $session = $this->flow->findSession($link, $this->cookieValue($request));

        FraudFlag::query()->create([
            'user_id' => $link->user_id,
            'link_id' => $link->getKey(),
            'reason' => 'visitor_report',
            'details' => [
                'reason' => $choice,
                'choice' => $choice,
                'detail' => $request->filled('detail') ? $request->string('detail')->toString() : null,
                'campaign_id' => $session?->campaign_id,
                'ip' => $context->ip,
            ],
        ]);

        return response()->json(['message' => 'Thank you. Your report has been sent.']);
    }

    /**
     * Sample data for previewing the layout locally without a visit session:
     * "?preview=keyword|wait", optionally with "&state=..." (see PREVIEW_STATES) and "&video=youtube|vimeo".
     *
     * @return array<string, mixed>|null
     */
    private function preview(Request $request): ?array
    {
        $mode = $request->query('preview');

        if (! app()->isLocal() || ! in_array($mode, ['keyword', 'wait'], true)) {
            return null;
        }

        $state = $request->query('state');
        $state = in_array($state, self::PREVIEW_STATES[$mode], true) ? $state : self::PREVIEW_STATES[$mode][0];

        return [
            ...($mode === 'keyword' ? $this->keywordPreview($state, $request->query('video')) : $this->waitPreview($state)),
            'preview_state' => $state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function keywordPreview(string $state, mixed $video): array
    {
        return [
            'mode' => 'keyword',
            'keyword' => 'how to repot a houseplant',
            'display_domain' => 'example-adv***.com',
            'images' => ['google' => null, 'button' => null, 'position' => null],
            'session_seconds_left' => match ($state) {
                'low' => 45,
                'expired' => 0,
                default => 600,
            },
            'tutorial_video_url' => match ($video) {
                'youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'vimeo' => 'https://vimeo.com/76979871',
                default => '',
            },
            'captcha_required' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function waitPreview(string $state): array
    {
        return [
            'mode' => 'wait',
            'step' => $state === 'final' ? 3 : 1,
            'total_steps' => 3,
            'dwell_seconds' => $state === 'counting' ? 15 : 0,
            'token' => 'preview',
            'captcha_required' => $state === 'final',
        ];
    }

    private function stepResponse(StepInfo $info): JsonResponse
    {
        if (! $info->accepted()) {
            return $this->errorResponse($info->result);
        }

        return response()->json([
            'mode' => $info->mode->value,
            'step' => $info->step,
            'total_steps' => $info->totalSteps,
            'dwell_seconds' => $info->dwellSeconds,
            'token' => $info->token,
            'captcha_required' => $info->captchaRequired,
            ...($info->keyword ?? []),
        ]);
    }

    private function codeErrorResponse(CodeResult $result, VisitSession $session): JsonResponse
    {
        return response()->json([
            'error' => $result->errorKey(),
            'message' => $result->label(),
            'failures_left' => CodeRedeemer::failuresLeft($session),
        ], 422);
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
