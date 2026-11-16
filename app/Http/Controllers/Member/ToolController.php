<?php

namespace App\Http\Controllers\Member;

use App\Exceptions\InvalidLinkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\MassShrinkRequest;
use App\Http\Requests\Member\RegenerateApiTokenRequest;
use App\Models\User;
use App\Services\LinkShortener;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ToolController extends Controller
{
    public const UNREADABLE_TOKEN_MESSAGE = 'Your API token could not be read. Generate a new token below.';

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
     * The token is empty and tokenError is set when the stored token cannot be decrypted (for example after an APP_KEY change).
     *
     * @return array{token: string, tokenError: ?string, baseUrl: string, level: int}
     */
    private function toolData(): array
    {
        /** @var User $member */
        $member = auth()->user();

        try {
            $token = (string) $member->api_token;
            $tokenError = null;
        } catch (DecryptException) {
            $token = '';
            $tokenError = self::UNREADABLE_TOKEN_MESSAGE;
        }

        return [
            'token' => $token,
            'tokenError' => $tokenError,
            'baseUrl' => rtrim(url('/'), '/'),
            'level' => (int) $member->default_level,
        ];
    }
}
