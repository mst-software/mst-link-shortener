<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ChangePasswordRequest;
use App\Models\User;
use App\Services\AccountCredentialService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('member.user.change-password');
    }

    public function update(ChangePasswordRequest $request, AccountCredentialService $credentials): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $credentials->changePassword($user, $request->validated('password'));

        return redirect()->route('member.user.change-password')->with('success', 'Saved.');
    }
}
