<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BanLinkRequest;
use App\Models\FraudFlag;
use App\Services\LinkBanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FraudFlagController extends Controller
{
    public function index(Request $request): View
    {
        $showResolved = $request->boolean('resolved');

        return view('admin.fraud.index', [
            'flags' => FraudFlag::query()
                ->with(['user:id,username', 'link:id,alias,status'])
                ->when(! $showResolved, fn ($query) => $query->whereNull('resolved_at'))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'showResolved' => $showResolved,
        ]);
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
