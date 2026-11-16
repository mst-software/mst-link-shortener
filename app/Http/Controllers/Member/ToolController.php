<?php

namespace App\Http\Controllers\Member;

use App\Exceptions\InvalidLinkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\MassShrinkRequest;
use App\Http\Requests\Member\RegenerateApiTokenRequest;
use App\Models\User;
use App\Services\LinkShortener;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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
     * Replace the member's API token, for example after it leaked. The old token stops working at once.
     */
    public function regenerateApiToken(RegenerateApiTokenRequest $request): RedirectResponse
    {
        /** @var User $member */
        $member = $request->user();

        $member->regenerateApiToken();

        return redirect()->route('member.tools.api')
            ->with('success', 'Your API token was regenerated. Update every website and script that uses the old token.');
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
