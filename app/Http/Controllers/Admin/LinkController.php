<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LinkStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BanLinkRequest;
use App\Models\Link;
use App\Services\LinkBanService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = LinkStatus::tryFrom((string) $request->query('status', ''));

        return view('admin.links.index', [
            'links' => Link::query()
                ->with('user:id,username')
                ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('alias', 'like', '%'.$search.'%')->orWhere('url', 'like', '%'.$search.'%');
                }))
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function ban(BanLinkRequest $request, Link $link, LinkBanService $bans): RedirectResponse
    {
        $bans->ban($link, $request->validated('reason'));

        return redirect()->back()->with('success', 'Link banned.');
    }
}
