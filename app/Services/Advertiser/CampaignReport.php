<?php

namespace App\Services\Advertiser;

use App\Enums\ViewStatus;
use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Models\View;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Numbers an advertiser sees about one campaign. "Counted" is a view that pays the publisher
 * (pending or released); every other view, including cancelled ones, is "not counted".
 * The reason a view was not counted is never exposed here.
 */
class CampaignReport
{
    /**
     * @var list<ViewStatus>
     */
    private const COUNTED = [ViewStatus::Pending, ViewStatus::Released];

    public function __construct(private readonly Campaign $campaign) {}

    /**
     * @return array{started: int, issued: int, counted: int, not_counted: int, spent_micro: int}
     */
    public function funnel(CarbonInterface $from, CarbonInterface $to): array
    {
        $attempts = CodeAttempt::query()->where('campaign_id', $this->campaign->getKey());
        $views = View::query()->where('campaign_id', $this->campaign->getKey())->whereBetween('created_at', [$from, $to]);

        return [
            'started' => (clone $attempts)->whereBetween('started_at', [$from, $to])->count(),
            'issued' => (clone $attempts)->whereBetween('issued_at', [$from, $to])->count(),
            'counted' => (clone $views)->whereIn('status', self::COUNTED)->count(),
            'not_counted' => (clone $views)->whereNotIn('status', self::COUNTED)->count(),
            'spent_micro' => (int) (clone $views)->where('status', '!=', ViewStatus::Cancelled)->sum('advertiser_cost_micro'),
        ];
    }

    /**
     * One row per day of the range, including days without traffic.
     *
     * @return list<array{date: string, started: int, issued: int, counted: int}>
     */
    public function daily(CarbonInterface $from, CarbonInterface $to): array
    {
        $started = $this->countByDay(CodeAttempt::query()->where('campaign_id', $this->campaign->getKey()), 'started_at', $from, $to);
        $issued = $this->countByDay(CodeAttempt::query()->where('campaign_id', $this->campaign->getKey()), 'issued_at', $from, $to);
        $counted = $this->countByDay(
            View::query()->where('campaign_id', $this->campaign->getKey())->whereIn('status', self::COUNTED),
            'created_at',
            $from,
            $to,
        );

        $series = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();

            $series[] = [
                'date' => $date,
                'started' => $started[$date] ?? 0,
                'issued' => $issued[$date] ?? 0,
                'counted' => $counted[$date] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * The campaign's views for the visits table, newest first. Only fields an advertiser may see are selected.
     *
     * @param  'counted'|'not_counted'|null  $status
     * @return Builder<View>
     */
    public function visits(?string $status = null): Builder
    {
        return View::query()
            ->where('campaign_id', $this->campaign->getKey())
            ->when($status === 'counted', fn (Builder $query) => $query->whereIn('status', self::COUNTED))
            ->when($status === 'not_counted', fn (Builder $query) => $query->whereNotIn('status', self::COUNTED))
            ->select(['id', 'created_at', 'country', 'status', 'advertiser_cost_micro'])
            ->latest('id');
    }

    /**
     * Whether a view counts, as the advertiser sees it.
     */
    public static function isCounted(View $view): bool
    {
        return in_array($view->status, self::COUNTED, true);
    }

    /**
     * @param  Builder<*>  $query
     * @return array<string, int>
     */
    private function countByDay(Builder $query, string $column, CarbonInterface $from, CarbonInterface $to): array
    {
        return $query
            ->whereBetween($column, [$from, $to])
            ->selectRaw('DATE('.$column.') as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }
}
