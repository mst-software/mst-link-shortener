<?php

namespace App\Services\Views;

use App\Contracts\CaptchaVerifier;
use App\Enums\CaptchaProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shared "siteverify" call: Turnstile, hCaptcha and reCAPTCHA take the same form fields
 * (secret, response, remoteip) and answer JSON with a boolean "success".
 */
abstract class HttpCaptchaVerifier implements CaptchaVerifier
{
    private const TIMEOUT_SECONDS = 5;

    abstract protected function provider(): CaptchaProvider;

    abstract protected function endpoint(): string;

    public function verify(string $response, string $ip): bool
    {
        $secret = $this->provider()->secretKey();

        if ($response === '') {
            return false;
        }

        if ($secret === '') {
            Log::warning('Captcha secret key is not configured.', ['provider' => $this->provider()->value]);

            return false;
        }

        try {
            $result = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($this->endpoint(), [
                    'secret' => $secret,
                    'response' => $response,
                    'remoteip' => $ip,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Captcha verification request failed.', [
                'provider' => $this->provider()->value,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return $result->successful() && $result->json('success') === true;
    }
}
