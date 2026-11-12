<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\MonthRequest;
use App\Services\DashboardStats;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class StatisticController extends Controller
{
    public function index(MonthRequest $request): View
    {
        $month = $request->month();

        return view('member.statistic', [
            'month' => $month->format('m/Y'),
            'months' => DashboardStats::monthOptions($month, CarbonImmutable::now('UTC')),
            'dataDate' => $month->isCurrentMonth() ? CarbonImmutable::now('UTC')->format('d/m/Y') : $month->format('d/m/Y'),
        ]);
    }
}
