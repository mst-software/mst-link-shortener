<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect($this->dashboardUrl($user));
        }

        return view('auth.verify-email');
    }

    /**
     * Mark the email as verified from the signed link in the verification mail.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        /** @var User $user */
        $user = $request->user();

        return redirect($this->dashboardUrl($user))->with('success', 'Your email address has been verified.');
    }

    public function send(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect($this->dashboardUrl($user));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', 'A new verification link has been sent to your email address.');
    }

    private function dashboardUrl(User $user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isAdvertiser() => route('advertiser.dashboard'),
            default => route('member.dashboard'),
        };
    }
}
