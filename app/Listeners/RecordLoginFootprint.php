<?php

namespace App\Listeners;

use App\Enums\LoginEvent;
use App\Models\User;
use App\Services\LoginRecorder;
use Illuminate\Auth\Events\Login;

/**
 * Every sign in fires the guard's Login event (password, two-factor step, trusted device, remember-me cookie
 * and the sign in right after sign up), so this is the one place that writes the footprint. A user created in
 * this request is recorded as a sign up. Registered through Laravel's listener discovery.
 */
class RecordLoginFootprint
{
    public function __construct(private readonly LoginRecorder $recorder) {}

    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        $this->recorder->record($event->user, $event->user->wasRecentlyCreated ? LoginEvent::Signup : LoginEvent::Login);
    }
}
