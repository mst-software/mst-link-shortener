<?php

namespace App\Services\Views;

use App\Enums\CaptchaProvider;

/**
 * Verifies Google reCAPTCHA (v2 checkbox) responses.
 */
class RecaptchaVerifier extends HttpCaptchaVerifier
{
    protected function provider(): CaptchaProvider
    {
        return CaptchaProvider::Recaptcha;
    }

    protected function endpoint(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }
}
