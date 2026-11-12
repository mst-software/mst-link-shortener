<?php

namespace App\Http\Controllers\Member;

use App\Exceptions\InvalidLinkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\MassShrinkRequest;
use App\Services\LinkShortener;
use Illuminate\Contracts\View\View;

class ToolController extends Controller
{
    public function quick(): View
    {
        return view('member.tools.quick', $this->toolData());
    }

    public function massShrinker(): View
    {
        return view('member.tools.mass-shrinker', $this->toolData() + ['results' => null]);
    }

    public function massShrink(MassShrinkRequest $request, LinkShortener $shortener): View
    {
        $results = [];

        foreach ($request->urlLines() as $line) {
            try {
                $link = $shortener->create($request->user(), $line, null);
                $results[] = ['url' => $line, 'short_url' => url($link->alias), 'error' => null];
            } catch (InvalidLinkException $exception) {
                $results[] = ['url' => $line, 'short_url' => null, 'error' => $exception->getMessage()];
            }
        }

        return view('member.tools.mass-shrinker', $this->toolData() + ['results' => $results]);
    }

    public function fullPageScript(): View
    {
        return view('member.tools.full', $this->toolData());
    }

    public function api(): View
    {
        return view('member.tools.api', $this->toolData());
    }

    /**
     * @return array{token: string, baseUrl: string, level: int}
     */
    private function toolData(): array
    {
        $member = auth()->user();

        return [
            'token' => (string) $member->api_token,
            'baseUrl' => rtrim(url('/'), '/'),
            'level' => (int) $member->default_level,
        ];
    }
}
