<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Support\Alias;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LinkInfoController extends Controller
{
    /**
     * Statistics page of a single link, visible to its owner only.
     *
     * The per-day table is added by the aggregation task, which only has per-member rows today.
     */
    public function __invoke(Request $request, string $alias): View
    {
        abort_if(Alias::isReserved($alias), 404);

        $link = Link::query()->where('alias', $alias)->firstOrFail();

        abort_unless($link->user_id === $request->user()->id, 403);

        return view('links.info', [
            'link' => $link,
            'shortUrl' => url($link->alias),
        ]);
    }
}
