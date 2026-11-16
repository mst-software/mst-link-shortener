<?php

namespace App\Enums;

/**
 * How a sign-in footprint (user_logins row) was created.
 */
enum LoginEvent: string
{
    case Signup = 'signup';
    case Login = 'login';

    public function label(): string
    {
        return match ($this) {
            self::Signup => 'Sign up',
            self::Login => 'Sign in',
        };
    }
}
