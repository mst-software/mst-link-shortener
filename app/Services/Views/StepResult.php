<?php

namespace App\Services\Views;

enum StepResult: string
{
    case Accepted = 'accepted';
    case TooEarly = 'too_early';
    case BadToken = 'bad_token';
    case Expired = 'expired';
    case NoCookie = 'no_cookie';
    case NoFingerprint = 'no_fingerprint';
    case CaptchaFailed = 'captcha_failed';
    case ModeChanged = 'mode_changed';

    /**
     * Message shown to the visitor when the step request is rejected.
     */
    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'OK',
            self::TooEarly => 'Please wait until the countdown has finished.',
            self::BadToken => 'This page is no longer valid. Please open the link again.',
            self::Expired => 'Your session has expired. Please open the link again.',
            self::NoCookie => 'Please enable cookies in your browser and open the link again.',
            self::NoFingerprint => 'Please enable JavaScript in your browser and reload the page.',
            self::CaptchaFailed => 'Captcha verification failed. Please try again.',
            self::ModeChanged => 'This page has changed. Please reload it to continue.',
        };
    }
}
