<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Second sign in step for users with two-factor on. The password was already checked by LoginController,
 * which left a short-lived pending login in the session.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'login.two_factor';

    public const EXPIRES_IN_MINUTES = 5;

    public function show(Request $request): View|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return $this->restartLogin($request);
        }

        return view('auth.two-factor-challenge');
    }

    public function store(TwoFactorChallengeRequest $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return $this->restartLogin($request);
        }

        if (! $twoFactor->verify($user, $request->validated('code'))) {
            return back()->withErrors(['code' => 'The authentication code is invalid.']);
        }

        $remember = (bool) $request->session()->get(self::SESSION_KEY.'.remember');
        $request->session()->forget(self::SESSION_KEY);

        Auth::guard('web')->login($user, $remember);

        $request->session()->regenerate();

        $response = redirect()->intended($user->dashboardUrl());

        if ($request->boolean('trust_device')) {
            $token = $twoFactor->trustDevice($user, $request->ip(), $request->userAgent());

            $response->withCookie(cookie(
                TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE,
                $token,
                TwoFactorAuthentication::TRUSTED_DEVICE_DAYS * 24 * 60,
                secure: $request->secure(),
                httpOnly: true,
                sameSite: 'strict',
            ));
        }

        return $response;
    }

    /**
     * The user waiting for the second step, or null when there is none, it expired or the account changed.
     */
    private function pendingUser(Request $request): ?User
    {
        /** @var array{user_id?: int, remember?: bool, expires_at?: int}|null $pending */
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $user = User::query()->find($pending['user_id'] ?? null);

        if ($user === null || ! $user->isActive() || ! $user->hasTwoFactorEnabled()) {
            return null;
        }

        return $user;
    }

    private function restartLogin(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login')->withErrors(['username' => 'Your sign in session expired. Please sign in again.']);
    }
}
