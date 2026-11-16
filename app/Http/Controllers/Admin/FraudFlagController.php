<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BanLinkRequest;
use App\Models\FraudFlag;
use App\Models\User;
use App\Services\LinkBanService;
use App\Services\ReferralCommissionService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FraudFlagController extends Controller
{
    public function index(Request $request): View
    {
        $showResolved = $request->boolean('resolved');

        $flags = FraudFlag::query()
            ->with(['user:id,username', 'link:id,alias,status'])
            ->when(! $showResolved, fn ($query) => $query->whereNull('resolved_at'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $referredUserIds = $flags->getCollection()
            ->where('reason', ReferralCommissionService::FLAG_REASON)
            ->map(fn (FraudFlag $flag): int => (int) ($flag->details['referred_user_id'] ?? 0))
            ->filter()
            ->unique();

        return view('admin.fraud.index', [
            'flags' => $flags,
            'showResolved' => $showResolved,
            'referredUsers' => User::query()->whereKey($referredUserIds->all())->get(['id', 'username'])->keyBy('id'),
        ]);
    }

    public function payHeldCommission(FraudFlag $fraudFlag, Request $request, ReferralCommissionService $referralCommissions): RedirectResponse
    {
        $paid = $referralCommissions->payHeld($request->user(), $fraudFlag);

        return redirect()->back()->with('success', 'Held commission of '.Money::format($paid, 6).' paid to the referrer.');
    }

    public function resolve(FraudFlag $fraudFlag): RedirectResponse
    {
        $fraudFlag->update(['resolved_at' => $fraudFlag->resolved_at ?? now()]);

        return redirect()->back()->with('success', 'Flag marked as handled.');
    }

    public function banLink(BanLinkRequest $request, FraudFlag $fraudFlag, LinkBanService $bans): RedirectResponse
    {
        if ($fraudFlag->link === null) {
            return redirect()->back()->with('error', 'This flag has no link to ban.');
        }

        $bans->ban($fraudFlag->link, $request->validated('reason'));
        $fraudFlag->update(['resolved_at' => $fraudFlag->resolved_at ?? now()]);

        return redirect()->back()->with('success', 'Link banned.');
    }
}
