<?php

namespace App\Services\Views;

use App\Contracts\CaptchaVerifier;
use App\Enums\LinkStatus;
use App\Enums\TokenResult;
use App\Enums\VisitMode;
use App\Enums\VisitStatus;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\Link;
use App\Models\VisitSession;
use App\Services\Settings;
use App\Services\Visits\CampaignSelector;
use App\Services\Visits\CodeRedeemer;
use App\Services\Visits\CodeResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Server side state machine of the visitor page.
 *
 * A link of level N has N steps. Each step is unlocked by a signed one-time token that the
 * server issues and stamps with step_issued_at; the next token is only issued when the previous
 * one is presented after the dwell time, from the same user agent and browser fingerprint.
 * The token of the last step is redeemed by finish(), which checks the captcha and records the view.
 *
 * A session in keyword mode has no steps and no tokens: the visitor types a code that the widget on
 * the advertiser site issued, and finishKeyword() redeems it, checks the captcha and records the view.
 */
class VisitFlow
{
    public function __construct(
        private StepTokenService $tokens,
        private CaptchaVerifier $captcha,
        private ViewValidator $validator,
        private ViewRecorder $recorder,
        private CampaignSelector $campaigns,
        private CodeRedeemer $redeemer,
    ) {}

    /**
     * Find a link that visitors may open; hidden and banned links are treated as missing.
     */
    public function findActiveLink(string $alias): ?Link
    {
        return Link::query()
            ->where('alias', $alias)
            ->where('status', LinkStatus::Active)
            ->first();
    }

    /**
     * Find the visit session of the link referenced by the visitor cookie.
     */
    public function findSession(Link $link, ?string $sessionId): ?VisitSession
    {
        if ($sessionId === null || $sessionId === '') {
            return null;
        }

        return VisitSession::query()
            ->whereKey($sessionId)
            ->where('link_id', $link->getKey())
            ->first();
    }

    /**
     * Whether a session can be continued instead of starting a new one.
     */
    public function isResumable(VisitSession $session, VisitorContext $context): bool
    {
        return $session->status === VisitStatus::InProgress
            && $session->finished_at === null
            && $session->expires_at->isFuture()
            && hash_equals($session->ua_hash, $context->uaHash);
    }

    /**
     * Start a new visit session. Everything the rules need later is captured on the server.
     */
    public function start(Link $link, VisitorContext $context): VisitSession
    {
        $campaign = $this->campaigns->pick();

        return VisitSession::query()->create([
            'mode' => $campaign !== null ? VisitMode::Keyword : VisitMode::Wait,
            'campaign_id' => $campaign?->getKey(),
            'link_id' => $link->getKey(),
            'ip' => $context->ip,
            'ua_hash' => $context->uaHash,
            'country' => $context->country,
            'level' => $link->level,
            'current_step' => 1,
            'status' => VisitStatus::InProgress,
            'expires_at' => now()->addMinutes(Settings::int('session_minutes')),
        ]);
    }

    /**
     * Handle a "get link info" request: issue the token of the current step when no token is
     * given (first load or reload), or move to the next step when the token of the current step
     * is presented after the dwell time.
     */
    public function stepInfo(VisitSession $session, ?string $token, ?string $fingerprint, VisitorContext $context): StepInfo
    {
        if ($session->status !== VisitStatus::InProgress || $session->finished_at !== null) {
            return StepInfo::rejected(StepResult::Expired);
        }

        if (! $session->expires_at->isFuture()) {
            $session->forceFill(['status' => VisitStatus::Expired])->save();

            return StepInfo::rejected(StepResult::Expired);
        }

        if (! hash_equals($session->ua_hash, $context->uaHash)) {
            return StepInfo::rejected(StepResult::BadToken);
        }

        if ($fingerprint === null || $fingerprint === '') {
            return StepInfo::rejected(StepResult::NoFingerprint);
        }

        if ($session->fingerprint !== null && ! hash_equals($session->fingerprint, $fingerprint)) {
            return StepInfo::rejected(StepResult::BadToken);
        }

        // A changed IP is not a reason to block (mobile networks); it is remembered and makes the view invalid.
        if ($session->ip !== $context->ip && ! $session->ip_changed) {
            $session->forceFill(['ip_changed' => true])->save();
        }

        // A keyword session whose campaign is gone and whose device has no code continues in wait mode.
        $this->downgradeToWaitIfUnavailable($session);

        if ($session->mode === VisitMode::Keyword) {
            return $this->keywordInfo($session, $fingerprint);
        }

        if ($token === null || $token === '') {
            $session->fingerprint ??= $fingerprint;

            return $this->issue($session, $session->current_step);
        }

        // The last token is redeemed by the captcha check, never exchanged for another step.
        if ($session->current_step >= $session->level) {
            return StepInfo::rejected(StepResult::BadToken);
        }

        if (! $this->dwellPassed($session)) {
            return StepInfo::rejected(StepResult::TooEarly);
        }

        if ($this->tokens->verify($session, $session->current_step, $token, $context->uaHash) !== TokenResult::Valid) {
            return StepInfo::rejected(StepResult::BadToken);
        }

        $session->current_step = $session->current_step + 1;

        return $this->issue($session, $session->current_step);
    }

    /**
     * Handle the last step: redeem the token of the current step, verify the captcha, run every
     * rule and record the view. A view that breaks a rule is still recorded (unpaid, with its
     * reason) and the visitor still gets the destination. Calling it again for a finished session
     * returns the same view without writing anything.
     *
     * @param  bool  $hasCookie  Whether the visit cookie of this session came back with the request.
     */
    public function finish(
        VisitSession $session,
        string $token,
        ?string $fingerprint,
        ?string $captchaResponse,
        bool $hasCookie,
        VisitorContext $context,
    ): FinishResult {
        if (! hash_equals($session->ua_hash, $context->uaHash)) {
            return FinishResult::rejected(StepResult::BadToken);
        }

        if ($session->finished_at !== null) {
            return $this->finishedResult($session);
        }

        if ($session->status !== VisitStatus::InProgress || ! $session->expires_at->isFuture()) {
            return FinishResult::rejected(StepResult::Expired);
        }

        if ($this->tokens->verify($session, $session->current_step, $token, $context->uaHash) !== TokenResult::Valid) {
            // A concurrent request with the same token may have finished the session meanwhile.
            $session->refresh();

            return $session->finished_at !== null
                ? $this->finishedResult($session)
                : FinishResult::rejected(StepResult::BadToken);
        }

        $hasJavascript = $fingerprint !== null
            && $fingerprint !== ''
            && $session->fingerprint !== null
            && hash_equals($session->fingerprint, $fingerprint);

        $captchaOk = $this->captcha->verify((string) $captchaResponse, $context->ip);

        $reason = $this->validator->firstFailure($session, $context, $captchaOk, $hasCookie, $hasJavascript);

        return FinishResult::finished($this->recorder->record($session, $context, $reason));
    }

    /**
     * Handle the code step of a keyword session: check the captcha first (a wrong captcha never
     * touches the code), redeem the code, run every rule and record the view. A redeemed code is
     * consumed for good, so a view that breaks a later rule is still recorded (unpaid, with its
     * reason) and the visitor still gets the destination.
     *
     * @param  bool  $hasCookie  Whether the visit cookie of this session came back with the request.
     */
    public function finishKeyword(
        VisitSession $session,
        string $code,
        ?string $fingerprint,
        ?string $captchaResponse,
        bool $hasCookie,
        VisitorContext $context,
    ): FinishResult {
        if (! hash_equals($session->ua_hash, $context->uaHash)) {
            return FinishResult::rejected(StepResult::BadToken);
        }

        if ($session->finished_at !== null) {
            return $this->finishedResult($session);
        }

        if ($session->code_failures >= CodeRedeemer::MAX_SESSION_FAILURES) {
            return FinishResult::codeRejected(CodeResult::Locked);
        }

        if ($session->status !== VisitStatus::InProgress || ! $session->expires_at->isFuture()) {
            return FinishResult::rejected(StepResult::Expired);
        }

        if ($this->downgradeToWaitIfUnavailable($session)) {
            return FinishResult::rejected(StepResult::ModeChanged);
        }

        if ($this->sessionSecondsLeft($session) <= 0) {
            return FinishResult::rejected(StepResult::Expired);
        }

        if (! $this->captcha->verify((string) $captchaResponse, $context->ip)) {
            return FinishResult::rejected(StepResult::CaptchaFailed);
        }

        $result = $this->redeemer->redeem($session, $code, $context);

        // The redeemer works on a locked copy of the row; reload to see its changes (failures, ip_changed).
        $session->refresh();

        if ($result !== CodeResult::Accepted) {
            return FinishResult::codeRejected($result);
        }

        $hasJavascript = $fingerprint !== null
            && $fingerprint !== ''
            && $session->fingerprint !== null
            && hash_equals($session->fingerprint, $fingerprint);

        $reason = $this->validator->firstFailure($session, $context, true, $hasCookie, $hasJavascript);

        return FinishResult::finished($this->recorder->record($session, $context, $reason));
    }

    /**
     * Move a keyword session to wait mode when its campaign can no longer serve it (switched off,
     * outside its dates or over its daily limit) and this device holds no usable code.
     * Returns true when the session was changed.
     */
    public function downgradeToWaitIfUnavailable(VisitSession $session): bool
    {
        if ($session->mode !== VisitMode::Keyword) {
            return false;
        }

        $campaign = $session->campaign_id !== null
            ? Campaign::query()->available()->find($session->campaign_id)
            : null;

        $hasCapacity = $campaign !== null
            && ($campaign->daily_limit === 0 || $campaign->issuedToday() < $campaign->daily_limit);

        if ($hasCapacity || $this->deviceHoldsCode($session)) {
            return false;
        }

        $session->forceFill(['mode' => VisitMode::Wait])->save();

        return true;
    }

    /**
     * Seconds left of the code session, counted on the server clock from the creation of the visit session.
     */
    public function sessionSecondsLeft(VisitSession $session): int
    {
        $endsAt = $session->created_at->getTimestamp() + max(0, Settings::int('code_session_seconds'));

        return max(0, $endsAt - now()->getTimestamp());
    }

    /**
     * Whether the device of the session got a code of its campaign that it can still use: not
     * expired and not used by another session, or already used by this session.
     */
    private function deviceHoldsCode(VisitSession $session): bool
    {
        if ($session->campaign_id === null) {
            return false;
        }

        return CodeAttempt::query()
            ->where('campaign_id', $session->campaign_id)
            ->where('ua_hash', $session->ua_hash)
            ->whereNotNull('code')
            ->where(function (Builder $query) use ($session): void {
                $query->where(fn (Builder $unused) => $unused->whereNull('used_at')->where('expires_at', '>', now()))
                    ->orWhere('visit_session_id', $session->getKey());
            })
            ->exists();
    }

    private function keywordInfo(VisitSession $session, ?string $fingerprint): StepInfo
    {
        if ($session->fingerprint === null) {
            $session->forceFill(['fingerprint' => $fingerprint])->save();
        }

        $campaign = Campaign::query()->findOrFail($session->campaign_id);

        return new StepInfo(
            result: StepResult::Accepted,
            step: 1,
            totalSteps: 1,
            dwellSeconds: 0,
            captchaRequired: true,
            mode: VisitMode::Keyword,
            keyword: [
                'keyword' => $campaign->keyword,
                'display_domain' => $campaign->display_domain,
                'images' => [
                    'google' => $this->imageUrl($campaign->image_google),
                    'button' => $this->imageUrl($campaign->image_button),
                    'position' => $this->imageUrl($campaign->image_position),
                ],
                'session_seconds_left' => $this->sessionSecondsLeft($session),
                'tutorial_video_url' => (string) Settings::get('tutorial_video_url', ''),
            ],
        );
    }

    private function imageUrl(?string $path): ?string
    {
        return $path !== null && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Whether the dwell time of the current step has passed, measured with the server clock only.
     */
    public function dwellPassed(VisitSession $session): bool
    {
        if ($session->step_issued_at === null) {
            return false;
        }

        return now()->getTimestamp() >= $session->step_issued_at->getTimestamp() + $this->dwellSeconds();
    }

    public function dwellSeconds(): int
    {
        return max(0, Settings::int('dwell_seconds'));
    }

    private function finishedResult(VisitSession $session): FinishResult
    {
        $view = $session->view()->first();

        return $view !== null
            ? FinishResult::finished($view)
            : FinishResult::rejected(StepResult::Expired);
    }

    private function issue(VisitSession $session, int $step): StepInfo
    {
        $token = $this->tokens->issue($session, $step);

        return new StepInfo(
            result: StepResult::Accepted,
            step: $step,
            totalSteps: $session->level,
            dwellSeconds: $this->dwellSeconds(),
            token: $token,
            captchaRequired: $step >= $session->level,
        );
    }
}
