<?php

namespace App\Services;

use App\Enums\ViewStatus;
use App\Models\DailyStat;
use App\Models\Link;
use App\Models\User;
use App\Models\View;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardStats
{
    /**
     * Parse a strict date string such as "13/11/2026" (d/m/Y) or "11/2026" (m/Y) as a UTC day.
     * Returns null when the text is malformed or not a real date.
     */
    public static function parse(string $value, string $format): ?CarbonImmutable
    {
        if (! CarbonImmutable::canBeCreatedFromFormat($value, $format)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!'.$format, $value, 'UTC');

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    /**
     * Statistics boxes are shown as "$ 0.050" (with a space), unlike Money::format().
     */
    public static function display(int $micro): string
    {
        return str_replace('$', '$ ', Money::format($micro));
    }

    /**
     * Month choices for the picker: the last 12 months plus the selected one, newest first, as "m/Y".
     *
     * @return list<string>
     */
    public static function monthOptions(CarbonImmutable $selected, CarbonImmutable $now): array
    {
        $options = [];

        for ($i = 0; $i < 12; $i++) {
            $options[] = $now->startOfMonth()->subMonthsNoOverflow($i)->format('m/Y');
        }

        if (! in_array($selected->format('m/Y'), $options, true)) {
            $options[] = $selected->format('m/Y');
        }

        return $options;
    }

    /**
     * Earnings per 1,000 valid views in micro-USD.
     */
    public static function cpm(int $earnMicro, int $views): int
    {
        return $views > 0 ? (int) round($earnMicro * 1000 / $views) : 0;
    }

    /**
     * Daily totals for the calendar month (UTC days) that contains $month, from daily_stats.
     *
     * @return array{
     *     days: list<array{date: string, views: int, earn_micro: int, ref_earn_micro: int, cpm_micro: int}>,
     *     total_views: int,
     *     total_earn_micro: int,
     *     total_ref_earn_micro: int,
     *     total_income_micro: int,
     *     average_cpm_micro: int,
     * }
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $stats = DailyStat::query()
            ->where('user_id', $user->id)
            ->whereBetween('stat_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->orderBy('stat_date')
            ->get();

        $days = $stats->map(fn (DailyStat $stat): array => [
            'date' => $stat->stat_date->toDateString(),
            'views' => $stat->views,
            'earn_micro' => $stat->earn_micro,
            'ref_earn_micro' => $stat->ref_earn_micro,
            'cpm_micro' => self::cpm($stat->earn_micro, $stat->views),
        ])->all();

        $totalViews = (int) $stats->sum('views');
        $totalEarn = (int) $stats->sum('earn_micro');
        $totalRefEarn = (int) $stats->sum('ref_earn_micro');

        return [
            'days' => $days,
            'total_views' => $totalViews,
            'total_earn_micro' => $totalEarn,
            'total_ref_earn_micro' => $totalRefEarn,
            'total_income_micro' => $totalEarn + $totalRefEarn,
            'average_cpm_micro' => self::cpm($totalEarn, $totalViews),
        ];
    }

    /**
     * Income (own earnings plus referral commission) for today, yesterday and the current month.
     *
     * @return array{today: int, yesterday: int, month: int}
     */
    public function overview(User $user, CarbonImmutable $today): array
    {
        $todayKey = $today->toDateString();
        $yesterdayKey = $today->subDay()->toDateString();
        $monthStart = $today->startOfMonth()->toDateString();

        $rows = DailyStat::query()
            ->where('user_id', $user->id)
            ->whereBetween('stat_date', [min($monthStart, $yesterdayKey), $todayKey])
            ->get();

        $income = fn (DailyStat $stat): int => $stat->earn_micro + $stat->ref_earn_micro;
        $on = fn (string $date): int => (int) $rows
            ->filter(fn (DailyStat $stat): bool => $stat->stat_date->toDateString() === $date)
            ->sum($income);

        return [
            'today' => $on($todayKey),
            'yesterday' => $on($yesterdayKey),
            'month' => (int) $rows
                ->filter(fn (DailyStat $stat): bool => $stat->stat_date->toDateString() >= $monthStart)
                ->sum($income),
        ];
    }

    /**
     * The member's best links in a month, ranked by valid views and then earnings.
     *
     * @return Collection<int, array{link: Link, views: int, earn_micro: int}>
     */
    public function topLinks(User $user, CarbonImmutable $month, int $limit = 10): Collection
    {
        $rows = View::query()
            ->selectRaw('link_id, count(*) as total_views, sum(earn_micro) as total_earn_micro')
            ->where('user_id', $user->id)
            ->whereIn('status', [ViewStatus::Pending, ViewStatus::Released])
            ->whereBetween('created_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->groupBy('link_id')
            ->orderByDesc('total_views')
            ->orderByDesc('total_earn_micro')
            ->orderBy('link_id')
            ->limit($limit)
            ->get();

        $links = Link::query()->whereKey($rows->pluck('link_id'))->get()->keyBy('id');

        return $rows
            ->filter(fn (View $row): bool => $links->has($row->link_id))
            ->map(fn (View $row): array => [
                'link' => $links->get($row->link_id),
                'views' => (int) $row->getAttribute('total_views'),
                'earn_micro' => (int) $row->getAttribute('total_earn_micro'),
            ])
            ->values();
    }

    /**
     * Valid views and earnings of one link per UTC day for the last 30 days (today included), newest first.
     *
     * @return list<array{date: string, views: int, earn_micro: int}>
     */
    public function linkDays(Link $link, CarbonImmutable $today): array
    {
        return View::query()
            ->selectRaw('date(created_at) as stat_day, count(*) as total_views, sum(earn_micro) as total_earn_micro')
            ->where('link_id', $link->id)
            ->whereIn('status', [ViewStatus::Pending, ViewStatus::Released])
            ->where('created_at', '>=', $today->subDays(29)->startOfDay())
            ->groupBy('stat_day')
            ->orderByDesc('stat_day')
            ->get()
            ->map(fn (View $row): array => [
                'date' => (string) $row->getAttribute('stat_day'),
                'views' => (int) $row->getAttribute('total_views'),
                'earn_micro' => (int) $row->getAttribute('total_earn_micro'),
            ])
            ->all();
    }
}
