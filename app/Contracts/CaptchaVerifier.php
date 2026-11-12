<?php

namespace App\Contracts;

interface CaptchaVerifier
{
    /**
     * Verify a captcha response token with the provider, server side.
     */
    public function verify(string $response, string $ip): bool;
}
