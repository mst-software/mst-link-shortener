<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlockedNetworkRequest;
use App\Models\BlockedNetwork;
use App\Services\Views\BlockedNetworkChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BlockedNetworkController extends Controller
{
    public function __construct(private readonly BlockedNetworkChecker $checker) {}

    public function index(): View
    {
        return view('admin.networks.index', [
            'networks' => BlockedNetwork::query()->latest('id')->paginate(50),
        ]);
    }

    public function store(StoreBlockedNetworkRequest $request): RedirectResponse
    {
        BlockedNetwork::query()->updateOrCreate(
            ['cidr' => $request->validated('cidr')],
            ['label' => $request->validated('label')],
        );
        $this->checker->clearCache();

        return redirect()->route('admin.blocked-networks.index')->with('success', 'Network blocked.');
    }

    public function update(StoreBlockedNetworkRequest $request, BlockedNetwork $blockedNetwork): RedirectResponse
    {
        $blockedNetwork->update(['label' => $request->validated('label')]);

        return redirect()->route('admin.blocked-networks.index')->with('success', 'Label updated.');
    }

    public function destroy(BlockedNetwork $blockedNetwork): RedirectResponse
    {
        $blockedNetwork->delete();
        $this->checker->clearCache();

        return redirect()->route('admin.blocked-networks.index')->with('success', 'Network unblocked.');
    }
}
