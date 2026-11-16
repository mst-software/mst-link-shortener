<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\ChangeEmailRequest;
use App\Models\User;
use App\Services\AccountCredentialService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class EmailController extends Controller
{
    public function edit(): View
    {
        return view('member.user.change-email');
    }

    public function update(ChangeEmailRequest $request, AccountCredentialService $credentials): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $credentials->changeEmail($user, $request->validated('email'));

        return redirect()->route('member.user.change-email')
            ->with('success', 'Saved. We sent a verification link to your new email address.');
    }
}
