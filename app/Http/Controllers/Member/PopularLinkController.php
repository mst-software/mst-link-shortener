<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\MonthRequest;
use App\Services\DashboardStats;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class PopularLinkController extends Controller
{
    public function __construct(private readonly DashboardStats $stats) {}

    public function index(MonthRequest $request): View
    {
        $month = $request->month();

        return view('member.popular-link', [
            'month' => $month->format('m/Y'),
            'months' => DashboardStats::monthOptions($month, CarbonImmutable::now('UTC')),
            'links' => $this->stats->topLinks($request->user(), $month),
        ]);
    }
}
