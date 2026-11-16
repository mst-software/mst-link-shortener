<?php

namespace App\Enums;

/**
 * Reasons why two accounts look like they are run by the same person, with the points each one adds
 * to the multi-account score (capped at 100). Weights:
 * - same sign-up IP: 40 (both accounts were created from one address)
 * - same browser: 25 (identical user agent hash; weak alone, since popular browsers share it)
 * - shared sign-in IP: 20 (any other address both used within the lookback window)
 * - referral relation: 30 (one invited the other; only counted next to a footprint signal,
 *   because a referral alone is the normal case)
 *
 * Same sign-up IP + same browser + referral = 95, which is a high score.
 */
enum FootprintSignal: string
{
    case SameSignupIp = 'same_signup_ip';
    case SameDevice = 'same_device';
    case SharedLoginIp = 'shared_login_ip';
    case ReferralRelation = 'referral_relation';

    public function label(): string
    {
        return match ($this) {
            self::SameSignupIp => 'Same sign-up IP',
            self::SameDevice => 'Same browser',
            self::SharedLoginIp => 'Shared sign-in IP',
            self::ReferralRelation => 'Referral between them',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::SameSignupIp => 40,
            self::SameDevice => 25,
            self::SharedLoginIp => 20,
            self::ReferralRelation => 30,
        };
    }
}
