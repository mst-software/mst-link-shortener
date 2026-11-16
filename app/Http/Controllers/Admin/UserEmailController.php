<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserAccountActionRequest;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;

class UserEmailController extends Controller
{
    public function verify(UserAccountActionRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->markEmailVerified($request->user(), $user, $request->validated('reason'));

        return redirect()->route('admin.users.show', $user)->with('success', 'Email address marked as verified.');
    }

    public function sendVerification(UserAccountActionRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->sendVerificationEmail($request->user(), $user, $request->validated('reason'));

        return redirect()->route('admin.users.show', $user)->with('success', 'Verification email sent.');
    }

    public function sendPasswordReset(UserAccountActionRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->sendPasswordResetLink($request->user(), $user, $request->validated('reason'));

        return redirect()->route('admin.users.show', $user)->with('success', 'Password reset link sent.');
    }
}
