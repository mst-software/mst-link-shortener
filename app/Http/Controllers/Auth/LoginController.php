<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TwoFactorAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, TwoFactorAuthentication $twoFactor): RedirectResponse
    {
        $user = $request->validateCredentials();
        $remember = $request->boolean('remember_me');
        $isTrustedDevice = $twoFactor->isTrustedDevice($user, $request->cookie(TwoFactorAuthentication::TRUSTED_DEVICE_COOKIE));

        if ($user->hasTwoFactorEnabled() && ! $isTrustedDevice) {
            $request->session()->put(TwoFactorChallengeController::SESSION_KEY, [
                'user_id' => $user->getKey(),
                'remember' => $remember,
                'expires_at' => now()->addMinutes(TwoFactorChallengeController::EXPIRES_IN_MINUTES)->getTimestamp(),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->forget(TwoFactorChallengeController::SESSION_KEY);

        Auth::guard('web')->login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended($user->dashboardUrl());
    }
}
