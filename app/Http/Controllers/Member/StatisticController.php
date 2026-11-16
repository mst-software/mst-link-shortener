<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StatisticRequest;
use App\Services\DashboardStats;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class StatisticController extends Controller
{
    public function __construct(private readonly DashboardStats $stats) {}

    public function index(StatisticRequest $request): View
    {
        $month = $request->month();
        $range = $request->range();
        $now = CarbonImmutable::now('UTC');

        return view('member.statistic', [
            'month' => $month->format('m/Y'),
            'months' => DashboardStats::monthOptions($month, $now),
            'dataDate' => $month->isCurrentMonth() ? $now->format('d/m/Y') : $month->format('d/m/Y'),
            'range' => $range,
            'ranges' => DashboardStats::BREAKDOWN_RANGES,
            'breakdowns' => $this->stats->breakdowns($request->user(), $range, $now),
        ]);
    }
}
