<?php

namespace App\Services\Views;

use App\Enums\CaptchaProvider;

/**
 * Verifies hCaptcha responses.
 */
class HCaptchaVerifier extends HttpCaptchaVerifier
{
    protected function provider(): CaptchaProvider
    {
        return CaptchaProvider::HCaptcha;
    }

    protected function endpoint(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }
}
