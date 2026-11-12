<?php

namespace App\Services\Views;

use App\Enums\LinkStatus;
use App\Enums\TokenResult;
use App\Enums\VisitStatus;
use App\Models\Link;
use App\Models\VisitSession;
use App\Services\Settings;

/**
 * Server side state machine of the visitor page.
 *
 * A link of level N has N steps. Each step is unlocked by a signed one-time token that the
 * server issues and stamps with step_issued_at; the next token is only issued when the previous
 * one is presented after the dwell time, from the same user agent and browser fingerprint.
 * The token of the last step is redeemed by the captcha check (see ViewValidator), not here.
 */
class VisitFlow
{
    public function __construct(private StepTokenService $tokens) {}

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
        return VisitSession::query()->create([
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
