<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ConfirmTwoFactorRequest;
use App\Http\Requests\Member\VerifyTwoFactorRequest;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorAuthentication $twoFactor) {}

    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $isPending = ! $user->hasTwoFactorEnabled() && $user->two_factor_secret !== null;

        return view('member.user.two-factor', [
            'isEnabled' => $user->hasTwoFactorEnabled(),
            'isPending' => $isPending,
            'secret' => $isPending ? $user->two_factor_secret : null,
            'provisioningUri' => $isPending ? $this->twoFactor->provisioningUri($user) : null,
            'recoveryCodes' => $request->session()->get('recoveryCodes'),
        ]);
    }

    /**
     * Start the setup with a new secret; two-factor is not on until a code is confirmed.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            $this->twoFactor->beginSetup($user);
        }

        return redirect()->route('member.user.two-factor');
    }

    public function confirm(ConfirmTwoFactorRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $recoveryCodes = $this->twoFactor->confirm($user, $request->validated('code'));

        if ($recoveryCodes === null) {
            return back()->withErrors(['code' => 'The authentication code is invalid.']);
        }

        return redirect()->route('member.user.two-factor')
            ->with('success', 'Two-factor authentication is now on.')
            ->with('recoveryCodes', $recoveryCodes);
    }

    public function regenerateRecoveryCodes(VerifyTwoFactorRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return redirect()->route('member.user.two-factor')
            ->withCookie(cookie()->forget(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE))
            ->with('success', 'New recovery codes were created. The old ones and your remembered devices no longer work.')
            ->with('recoveryCodes', $this->twoFactor->regenerateRecoveryCodes($user));
    }

    public function destroy(VerifyTwoFactorRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->twoFactor->disable($user);

        return redirect()->route('member.user.two-factor')
            ->withCookie(cookie()->forget(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE))
            ->with('success', 'Two-factor authentication is now off.');
    }
}
