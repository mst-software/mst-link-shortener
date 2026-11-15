<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AdvertiserRegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.advertiser-register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['username', 'email', 'password']));
        $user->role = UserRole::Advertiser;
        $user->terms_accepted_at = now();
        $user->save();

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()->route('advertiser.dashboard');
    }
}
