<?php

namespace App\Http\Controllers\Advertiser;

use App\Enums\ReviewStatus;
use App\Enums\ViewStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\View as PageView;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $campaignIds = Campaign::query()->where('user_id', $user->id)->select('id');

        $spent = fn ($since): int => (int) PageView::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', '!=', ViewStatus::Cancelled)
            ->where('created_at', '>=', $since)
            ->sum('advertiser_cost_micro');

        $byStatus = Campaign::query()
            ->where('user_id', $user->id)
            ->selectRaw('review_status, count(*) as total')
            ->groupBy('review_status')
            ->pluck('total', 'review_status');

        return view('advertiser.dashboard', [
            'spentToday' => $spent(now()->startOfDay()),
            'spentWeek' => $spent(now()->subDays(6)->startOfDay()),
            'campaignCounts' => collect(ReviewStatus::cases())
                ->mapWithKeys(fn (ReviewStatus $status): array => [$status->label() => (int) ($byStatus[$status->value] ?? 0)]),
            'lowBalance' => $user->balance_micro < 10 * Settings::int('advertiser_default_price_micro'),
        ]);
    }
}
