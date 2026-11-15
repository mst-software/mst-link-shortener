<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TopUpStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\ViewStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\TopUp;
use App\Models\Transaction;
use App\Models\User;
use App\Models\View as PageView;
use App\Services\Advertiser\TopUpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AdvertiserController extends Controller
{
    public function index(): View
    {
        $advertisers = User::query()
            ->where('role', UserRole::Advertiser)
            ->withCount('campaigns')
            ->latest('id')
            ->paginate(20);

        $ids = $advertisers->getCollection()->modelKeys();

        $toppedUp = TopUp::query()
            ->whereIn('user_id', $ids)
            ->where('status', TopUpStatus::Completed)
            ->selectRaw('user_id, sum(amount_micro) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $spent = PageView::query()
            ->join('campaigns', 'campaigns.id', '=', 'views.campaign_id')
            ->whereIn('campaigns.user_id', $ids)
            ->where('views.status', '!=', ViewStatus::Cancelled)
            ->selectRaw('campaigns.user_id as owner_id, sum(views.advertiser_cost_micro) as total')
            ->groupBy('campaigns.user_id')
            ->pluck('total', 'owner_id');

        return view('admin.advertisers.index', [
            'advertisers' => $advertisers,
            'toppedUp' => $toppedUp,
            'spent' => $spent,
        ]);
    }

    public function show(User $advertiser): View
    {
        abort_unless($advertiser->isAdvertiser(), 404);

        $campaigns = Campaign::query()->where('user_id', $advertiser->id)->latest('id')->get();

        return view('admin.advertisers.show', [
            'advertiser' => $advertiser,
            'campaigns' => $campaigns,
            'topUps' => TopUp::query()->where('user_id', $advertiser->id)->latest('id')->limit(30)->get(),
            'transactions' => Transaction::query()->where('user_id', $advertiser->id)->latest('id')->limit(30)->get(),
            'visits' => PageView::query()
                ->with('campaign:id,name')
                ->whereIn('campaign_id', $campaigns->modelKeys())
                ->latest('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function toggle(User $advertiser): RedirectResponse
    {
        abort_unless($advertiser->isAdvertiser(), 404);

        $advertiser->forceFill([
            'status' => $advertiser->isActive() ? UserStatus::Banned : UserStatus::Active,
        ])->save();

        return redirect()->route('admin.advertisers.show', $advertiser)
            ->with('success', $advertiser->isActive() ? 'Advertiser unlocked.' : 'Advertiser locked.');
    }

    public function creditTopUp(TopUp $topUp, TopUpService $topUps): RedirectResponse
    {
        if ($topUp->status !== TopUpStatus::Mismatch) {
            return back()->with('error', 'Only top ups that need review can be credited by hand.');
        }

        $topUps->complete($topUp, allowMismatch: true);

        return back()->with('success', 'Top up credited to the wallet.');
    }
}
