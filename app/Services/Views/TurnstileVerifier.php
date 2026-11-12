<?php

namespace App\Services\Views;

use App\Enums\CaptchaProvider;

/**
 * Verifies Cloudflare Turnstile responses.
 */
class TurnstileVerifier extends HttpCaptchaVerifier
{
    protected function provider(): CaptchaProvider
    {
        return CaptchaProvider::Turnstile;
    }

    protected function endpoint(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }
}
