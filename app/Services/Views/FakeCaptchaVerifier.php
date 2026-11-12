<?php

namespace App\Services\Views;

use App\Contracts\CaptchaVerifier;

/**
 * Captcha verifier bound in the testing environment: any non-empty response passes,
 * except FakeCaptchaVerifier::FAILING_RESPONSE.
 */
class FakeCaptchaVerifier implements CaptchaVerifier
{
    public const FAILING_RESPONSE = 'fail';

    public function verify(string $response, string $ip): bool
    {
        return $response !== '' && $response !== self::FAILING_RESPONSE;
    }
}
