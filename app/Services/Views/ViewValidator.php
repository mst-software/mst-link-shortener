<?php

namespace App\Services\Views;

use App\Enums\InvalidReason;
use App\Enums\VisitMode;
use App\Models\CodeAttempt;
use App\Models\ViewDedup;
use App\Models\VisitSession;
use App\Services\Settings;

/**
 * Rules a finished visit must pass to be paid. Every input comes from the server:
 * the session row, the request context and the server side captcha result.
 */
class ViewValidator
{
    public function __construct(
        private BotUserAgentDetector $bots,
        private BlockedNetworkChecker $networks,
    ) {}

    /**
     * The first rule the visit breaks, in this order: NoCookie, NoJavascript, MissingSteps,
     * DwellTooShort, CaptchaFailed, Bot, BlockedNetwork, Duplicate, IpChanged. Null when it passes.
     * A keyword session has no steps and no dwell time: MissingSteps and DwellTooShort are replaced
     * by MissingCode and CodeInvalid, checked at the same place in the order.
     *
     * @param  bool  $hasCookie  Whether the visit cookie came back with the final request.
     * @param  bool  $hasJavascript  Whether the final request carried the session's browser fingerprint.
     */
    public function firstFailure(
        VisitSession $session,
        VisitorContext $context,
        bool $captchaOk,
        bool $hasCookie = true,
        bool $hasJavascript = true,
    ): ?InvalidReason {
        if (! $hasCookie) {
            return InvalidReason::NoCookie;
        }

        if (! $hasJavascript || $session->fingerprint === null) {
            return InvalidReason::NoJavascript;
        }

        if ($session->mode === VisitMode::Keyword) {
            $codeFailure = $this->codeFailure($session);

            if ($codeFailure !== null) {
                return $codeFailure;
            }
        } else {
            if ($session->current_step < $session->level) {
                return InvalidReason::MissingSteps;
            }

            if (! $this->dwellPassed($session)) {
                return InvalidReason::DwellTooShort;
            }
        }

        if (! $captchaOk) {
            return InvalidReason::CaptchaFailed;
        }

        if ($this->bots->isBot($context->userAgent)) {
            return InvalidReason::Bot;
        }

        if ($this->networks->isBlocked($context->ip) || $this->networks->isBlocked($session->ip)) {
            return InvalidReason::BlockedNetwork;
        }

        if ($this->isDuplicate($session)) {
            return InvalidReason::Duplicate;
        }

        if ($session->ip_changed || $session->ip !== $context->ip) {
            return InvalidReason::IpChanged;
        }

        return null;
    }

    /**
     * The code a keyword session redeemed must exist and belong to the campaign and device of the session.
     * The redeemer already guarantees this; the check keeps the view honest if it is ever bypassed.
     */
    private function codeFailure(VisitSession $session): ?InvalidReason
    {
        $attempt = CodeAttempt::query()
            ->where('visit_session_id', $session->getKey())
            ->whereNotNull('used_at')
            ->first();

        if ($attempt === null) {
            return InvalidReason::MissingCode;
        }

        if ($attempt->campaign_id !== $session->campaign_id || ! hash_equals($attempt->ua_hash, $session->ua_hash)) {
            return InvalidReason::CodeInvalid;
        }

        return null;
    }

    private function dwellPassed(VisitSession $session): bool
    {
        if ($session->step_issued_at === null) {
            return false;
        }

        return now()->getTimestamp() >= $session->step_issued_at->getTimestamp() + Settings::int('dwell_seconds');
    }

    private function isDuplicate(VisitSession $session): bool
    {
        return ViewDedup::query()
            ->where('key_hash', ViewDedup::keyFor($session->link_id, $session->ip, (string) $session->fingerprint))
            ->where('expires_at', '>', now())
            ->exists();
    }
}
