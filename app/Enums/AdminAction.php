<?php

namespace App\Enums;

/**
 * Actions an administrator can take that are written to the admin audit log.
 */
enum AdminAction: string
{
    case UserBanned = 'user_banned';
    case UserUnbanned = 'user_unbanned';
    case EmailVerified = 'email_verified';
    case VerificationEmailSent = 'verification_email_sent';
    case PasswordResetSent = 'password_reset_sent';
    case RoleChanged = 'role_changed';
    case BalanceAdjusted = 'balance_adjusted';

    public function label(): string
    {
        return match ($this) {
            self::UserBanned => 'Banned user',
            self::UserUnbanned => 'Unbanned user',
            self::EmailVerified => 'Marked email verified',
            self::VerificationEmailSent => 'Sent verification email',
            self::PasswordResetSent => 'Sent password reset link',
            self::RoleChanged => 'Changed role',
            self::BalanceAdjusted => 'Adjusted balance',
        };
    }
}
