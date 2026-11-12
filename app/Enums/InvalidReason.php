<?php

namespace App\Enums;

enum InvalidReason: string
{
    case MissingSteps = 'missing_steps';
    case DwellTooShort = 'dwell_too_short';
    case CaptchaFailed = 'captcha_failed';
    case Duplicate = 'duplicate';
    case BlockedNetwork = 'blocked_network';
    case Bot = 'bot';
    case IpChanged = 'ip_changed';
    case NoJavascript = 'no_javascript';
    case NoCookie = 'no_cookie';
    case MissingCode = 'missing_code';
    case CodeInvalid = 'code_invalid';

    public function label(): string
    {
        return match ($this) {
            self::MissingSteps => 'Missing steps',
            self::DwellTooShort => 'Dwell time too short',
            self::CaptchaFailed => 'Captcha failed',
            self::Duplicate => 'Duplicate view',
            self::BlockedNetwork => 'Blocked network',
            self::Bot => 'Bot',
            self::IpChanged => 'IP changed',
            self::NoJavascript => 'No JavaScript',
            self::NoCookie => 'No cookie',
            self::MissingCode => 'Missing code',
            self::CodeInvalid => 'Invalid code',
        };
    }
}
