<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['username', 'email', 'password']));

        $referralCode = $request->cookie('ref');

        if (is_string($referralCode)) {
            $user->referred_by = User::query()->where('referral_code', $referralCode)->value('id');
        }

        $user->save();

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()->route('member.dashboard')->withCookie(Cookie::forget('ref'));
    }
}
