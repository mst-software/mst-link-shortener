<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Self-service credential changes shared by the member and advertiser areas.
 */
class AccountCredentialService
{
    /**
     * Change the signed-in user's password and sign out every other session and device.
     * The remember-me token is replaced and trusted devices are forgotten. The AuthenticateSession
     * middleware rejects other sessions because their stored password hash no longer matches,
     * while logoutOtherDevices() refreshes the remember-me cookie of the current browser.
     */
    public function changePassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
        $user->save();

        $user->trustedDevices()->delete();

        $guard = Auth::guard('web');

        if ($guard->user()?->is($user)) {
            $guard->logoutOtherDevices($password);
        }
    }

    /**
     * Change the user's email address, mark it unverified and send a verification link to it.
     * A mail that cannot be sent is reported but never undoes the change.
     */
    public function changeEmail(User $user, string $email): void
    {
        $user->forceFill([
            'email' => $email,
            'email_verified_at' => null,
        ])->save();

        rescue(fn () => $user->sendEmailVerificationNotification());
    }
}
