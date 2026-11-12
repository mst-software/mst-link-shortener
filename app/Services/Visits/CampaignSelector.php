<?php

namespace App\Services\Visits;

use App\Enums\VisitMode;
use App\Models\Campaign;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Chooses the keyword campaign of a new visit session.
 */
class CampaignSelector
{
    /**
     * Pick a campaign at random, weighted by its weight, among the available campaigns that have
     * not reached their daily limit. Null when the visit mode is "wait" or no campaign qualifies.
     */
    public function pick(): ?Campaign
    {
        $mode = (string) Settings::get('visit_mode', 'auto');

        if ($mode === VisitMode::Wait->value) {
            return null;
        }

        $todayStart = now('UTC')->startOfDay();
        $todayEnd = now('UTC')->endOfDay();

        /** @var Collection<int, Campaign> $candidates */
        $candidates = Campaign::query()
            ->available()
            ->where('weight', '>', 0)
            ->withCount(['attempts as issued_today_count' => fn (Builder $attempts) => $attempts
                ->whereBetween('issued_at', [$todayStart, $todayEnd])])
            ->get()
            ->filter(fn (Campaign $campaign): bool => $campaign->daily_limit === 0
                || $campaign->getAttribute('issued_today_count') < $campaign->daily_limit)
            ->values();

        $totalWeight = $candidates->sum('weight');

        if ($totalWeight < 1) {
            return null;
        }

        $ticket = random_int(1, $totalWeight);

        foreach ($candidates as $campaign) {
            $ticket -= $campaign->weight;

            if ($ticket <= 0) {
                return $campaign;
            }
        }

        return $candidates->last();
    }
}
