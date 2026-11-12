<?php

namespace App\Http\Controllers;

use App\Enums\ViewStatus;
use App\Models\Link;
use App\Services\DashboardStats;
use App\Support\Alias;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LinkInfoController extends Controller
{
    public function __construct(private readonly DashboardStats $stats) {}

    /**
     * Statistics page of a single link, visible to its owner only: lifetime views and earnings
     * plus a per-day table for the last 30 days, read straight from the views table.
     */
    public function __invoke(Request $request, string $alias): View
    {
        abort_if(Alias::isReserved($alias), 404);

        $link = Link::query()->where('alias', $alias)->firstOrFail();

        abort_unless($link->user_id === $request->user()->id, 403);

        $days = $this->stats->linkDays($link, CarbonImmutable::now('UTC'));

        return view('links.info', [
            'link' => $link,
            'days' => $days,
            'totalEarnMicro' => (int) $link->views()
                ->whereIn('status', [ViewStatus::Pending, ViewStatus::Released])
                ->sum('earn_micro'),
            'shortUrl' => url($link->alias),
        ]);
    }
}
