<?php

namespace App\Http\Controllers\Member;

use App\Enums\LinkStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\LinkAliasRequest;
use App\Models\Link;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class LinkController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        return view('member.links.index', $this->listing($request, LinkStatus::Active));
    }

    public function hidden(Request $request): View
    {
        return view('member.links.hidden', $this->listing($request, LinkStatus::Hidden));
    }

    public function hide(LinkAliasRequest $request): RedirectResponse
    {
        $link = $this->ownedLink($request);

        if ($link->status === LinkStatus::Active) {
            $link->update(['status' => LinkStatus::Hidden]);
        }

        return redirect()->route('member.links')->with('success', 'The link has been hidden.');
    }

    public function restore(LinkAliasRequest $request): RedirectResponse
    {
        $link = $this->ownedLink($request);

        if ($link->status === LinkStatus::Hidden) {
            $link->update(['status' => LinkStatus::Active]);
        }

        return redirect()->route('member.links.hidden')->with('success', 'The link has been restored.');
    }

    /**
     * @return array{links: LengthAwarePaginator<int, Link>, alias: string}
     */
    private function listing(Request $request, LinkStatus $status): array
    {
        $alias = trim((string) $request->input('filter.alias', ''));

        $links = Link::query()
            ->whereBelongsTo($request->user())
            ->where('status', $status)
            ->when($alias !== '', fn ($query) => $query->where('alias', 'like', '%'.addcslashes($alias, '%_\\').'%'))
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return ['links' => $links, 'alias' => $alias];
    }

    private function ownedLink(LinkAliasRequest $request): Link
    {
        $link = Link::query()->where('alias', $request->string('alias')->toString())->firstOrFail();

        abort_unless($link->user_id === $request->user()->id, 403);

        return $link;
    }
}
