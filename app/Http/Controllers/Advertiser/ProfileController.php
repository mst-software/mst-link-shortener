<?php

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ChangeEmailRequest;
use App\Http\Requests\Member\ChangePasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function show(): View
    {
        return view('advertiser.profile');
    }

    public function password(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return redirect()->route('advertiser.profile')->with('success', 'Password changed.');
    }

    public function email(ChangeEmailRequest $request): RedirectResponse
    {
        $request->user()->update(['email' => $request->validated('email')]);

        return redirect()->route('advertiser.profile')->with('success', 'Email changed.');
    }
}
