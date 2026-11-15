<?php

namespace App\Services\Visits;

use App\Enums\VisitStatus;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\VisitSession;
use App\Services\Views\VisitorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Redeems the code a visitor typed against the code attempts issued by the widget.
 */
class CodeRedeemer
{
    /**
     * Wrong codes a session may enter before it is locked.
     */
    public const MAX_SESSION_FAILURES = 5;

    /**
     * Wrong codes one IP may enter per campaign within the decay window.
     */
    public const MAX_HOURLY_FAILURES = 10;

    private const HOURLY_DECAY_SECONDS = 3600;

    /**
     * Try to redeem a code for the session. One transaction holds a row lock on the session and
     * on the code attempt, so two concurrent requests with the same code cannot both win. A code
     * of another campaign is never found; a code issued to another device is refused but kept.
     */
    public function redeem(VisitSession $session, string $code, VisitorContext $context): CodeResult
    {
        $hourlyKey = $this->hourlyKey($session, $context);

        if (RateLimiter::tooManyAttempts($hourlyKey, self::MAX_HOURLY_FAILURES)) {
            return CodeResult::LockedHourly;
        }

        $code = strtolower(trim($code));

        return DB::transaction(function () use ($session, $code, $context, $hourlyKey): CodeResult {
            $locked = VisitSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->code_failures >= self::MAX_SESSION_FAILURES) {
                return CodeResult::Locked;
            }

            $campaign = $locked->campaign_id === null ? null : Campaign::query()->find($locked->campaign_id);

            // A campaign sent back to review, or one of a locked advertiser, no longer honors its codes.
            $attempt = $code === '' || $campaign === null || $campaign->isBlocked()
                ? null
                : CodeAttempt::query()
                    ->where('campaign_id', $locked->campaign_id)
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->first();

            if ($attempt === null) {
                return $this->fail($locked, $hourlyKey, CodeResult::Invalid);
            }

            if ($attempt->used_at !== null) {
                // A repeated request of the session that already redeemed the code is not an error.
                return $attempt->visit_session_id === $locked->getKey()
                    ? CodeResult::Accepted
                    : $this->fail($locked, $hourlyKey, CodeResult::Invalid);
            }

            // A code issued before the visit began cannot belong to this visit.
            if ($attempt->issued_at === null || $attempt->issued_at->lt($locked->created_at)) {
                return $this->fail($locked, $hourlyKey, CodeResult::Invalid);
            }

            if ($attempt->expires_at === null || ! $attempt->expires_at->isFuture()) {
                return $this->fail($locked, $hourlyKey, CodeResult::Expired);
            }

            if (! hash_equals($attempt->ua_hash, $context->uaHash)) {
                return $this->fail($locked, $hourlyKey, CodeResult::WrongDevice);
            }

            $attempt->forceFill([
                'used_at' => now(),
                'visit_session_id' => $locked->getKey(),
            ])->save();

            // A code fetched from another IP is accepted, but the view becomes invalid.
            if ($attempt->ip !== $locked->ip && ! $locked->ip_changed) {
                $locked->forceFill(['ip_changed' => true])->save();
            }

            return CodeResult::Accepted;
        });
    }

    /**
     * Wrong codes the session may still enter.
     */
    public static function failuresLeft(VisitSession $session): int
    {
        return max(0, self::MAX_SESSION_FAILURES - $session->code_failures);
    }

    /**
     * Count a failure on the session, lock the session at the limit and count it for the IP.
     */
    private function fail(VisitSession $locked, string $hourlyKey, CodeResult $result): CodeResult
    {
        $locked->forceFill(['code_failures' => $locked->code_failures + 1]);

        $isLocked = $locked->code_failures >= self::MAX_SESSION_FAILURES;

        if ($isLocked) {
            $locked->status = VisitStatus::Failed;
        }

        $locked->save();

        RateLimiter::hit($hourlyKey, self::HOURLY_DECAY_SECONDS);

        return $isLocked ? CodeResult::Locked : $result;
    }

    private function hourlyKey(VisitSession $session, VisitorContext $context): string
    {
        return 'code-failures:'.$context->ip.':'.($session->campaign_id ?? 0);
    }
}
