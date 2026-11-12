<?php

namespace App\Services\Views;

use App\Enums\InvalidReason;
use App\Enums\ViewStatus;
use App\Enums\VisitMode;
use App\Enums\VisitStatus;
use App\Models\Campaign;
use App\Models\Link;
use App\Models\View;
use App\Models\ViewDedup;
use App\Models\VisitSession;
use App\Services\Advertiser\AdvertiserBilling;
use App\Services\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Writes exactly one views row per finished visit session, valid or invalid.
 */
class ViewRecorder
{
    public function __construct(private RateResolver $rates, private AdvertiserBilling $billing) {}

    /**
     * Record the view of a session. Idempotent: a session that already has a view returns it.
     * The session row lock serializes concurrent finish requests and the unique index on
     * views.visit_session_id is the last guard against a second row.
     */
    public function record(VisitSession $session, VisitorContext $context, ?InvalidReason $reason): View
    {
        try {
            $view = DB::transaction(fn (): View => $this->recordLocked($session, $context, $reason));
        } catch (UniqueConstraintViolationException) {
            $view = View::query()->where('visit_session_id', $session->getKey())->firstOrFail();
        }

        $session->refresh();

        return $view;
    }

    private function recordLocked(VisitSession $session, VisitorContext $context, ?InvalidReason $reason): View
    {
        $locked = VisitSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

        $existing = View::query()->where('visit_session_id', $locked->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($reason === null && ! $this->claimDedup($locked)) {
            $reason = InvalidReason::Duplicate;
        }

        $isValid = $reason === null;
        $link = Link::query()->findOrFail($locked->link_id);
        $campaign = $locked->mode === VisitMode::Keyword && $locked->campaign_id !== null
            ? Campaign::query()->with('owner')->find($locked->campaign_id)
            : null;

        $view = View::query()->create([
            'link_id' => $link->getKey(),
            'user_id' => $link->user_id,
            'visit_session_id' => $locked->getKey(),
            'campaign_id' => $campaign?->getKey(),
            'ip' => $context->ip,
            'country' => $locked->country,
            'level' => $locked->level,
            'status' => $isValid ? ViewStatus::Pending : ViewStatus::Invalid,
            'invalid_reason' => $reason,
            'earn_micro' => $isValid ? $this->rates->ratePerViewMicro($locked->country, $locked->level) : 0,
            'matures_at' => now()->addDays(Settings::int('review_days')),
        ]);

        if ($isValid) {
            Link::query()->whereKey($link->getKey())->increment('views_count');
        }

        if ($campaign !== null) {
            $this->billing->chargeView($view, $campaign);
        }

        $locked->forceFill([
            'status' => $isValid ? VisitStatus::Completed : VisitStatus::Failed,
            'finished_at' => now(),
            'token_hash' => null,
        ])->save();

        return $view;
    }

    /**
     * Claim the dedup key for the unique window. Returns false when another paid view holds it.
     * insertOrIgnore on the unique key_hash makes the claim atomic between concurrent sessions.
     */
    private function claimDedup(VisitSession $session): bool
    {
        $key = ViewDedup::keyFor($session->link_id, $session->ip, (string) $session->fingerprint);

        ViewDedup::query()
            ->where('key_hash', $key)
            ->where('expires_at', '<=', now())
            ->delete();

        $inserted = ViewDedup::query()->insertOrIgnore([
            'key_hash' => $key,
            'expires_at' => now()->addHours(Settings::int('unique_window_hours')),
        ]);

        return $inserted === 1;
    }
}
