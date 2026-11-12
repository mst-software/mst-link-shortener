<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\DashboardDataRequest;
use App\Models\Notice;
use App\Services\DashboardStats;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStats $stats) {}

    public function index(Request $request): View
    {
        $member = $request->user();
        $income = $this->stats->overview($member, CarbonImmutable::now('UTC'));

        return view('member.dashboard', [
            'today' => DashboardStats::display($income['today']),
            'yesterday' => DashboardStats::display($income['yesterday']),
            'thisMonth' => DashboardStats::display($income['month']),
            'balance' => DashboardStats::display($member->balance_micro),
            'month' => CarbonImmutable::now('UTC')->format('m/Y'),
            'notices' => Notice::query()->published()->limit(5)->get(),
        ]);
    }

    /**
     * Chart and totals for the month that contains the posted date.
     */
    public function data(DashboardDataRequest $request): JsonResponse
    {
        $stats = $this->stats->forMonth($request->user(), $request->month());
        $days = collect($stats['days']);

        $series = fn (string $key, bool $money): array => $days
            ->map(fn (array $day): array => [
                'date' => CarbonImmutable::parse($day['date'])->format('d/m/Y'),
                'value' => $money ? $day[$key] / Money::MICRO_PER_USD : $day[$key],
            ])
            ->all();

        return response()->json([
            'success' => true,
            'info' => '',
            'pay_view_data' => $series('views', false),
            'publisher_earn_data' => $series('earn_micro', true),
            'cpm_data' => $series('cpm_micro', true),
            'ref_data' => $series('ref_earn_micro', true),
            'total_view' => $stats['total_views'],
            'total_publisher_earn' => Money::format($stats['total_earn_micro']),
            'total_ref_earn' => Money::format($stats['total_ref_earn_micro']),
            'total_income' => Money::format($stats['total_income_micro']),
            'average_cpm' => Money::format($stats['average_cpm_micro']),
        ]);
    }
}
