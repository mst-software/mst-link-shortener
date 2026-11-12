<?php

namespace App\Enums;

use App\Contracts\CaptchaVerifier;
use App\Services\Settings;
use App\Services\Views\HCaptchaVerifier;
use App\Services\Views\RecaptchaVerifier;
use App\Services\Views\TurnstileVerifier;

enum CaptchaProvider: string
{
    case Turnstile = 'turnstile';
    case HCaptcha = 'hcaptcha';
    case Recaptcha = 'recaptcha';

    /**
     * The provider chosen in the "captcha_provider" setting, Turnstile when unset or unknown.
     */
    public static function current(): self
    {
        return self::tryFrom((string) Settings::get('captcha_provider')) ?? self::Turnstile;
    }

    /**
     * The verifier that checks responses of this provider with its siteverify API.
     */
    public function verifier(): CaptchaVerifier
    {
        return match ($this) {
            self::Turnstile => app(TurnstileVerifier::class),
            self::HCaptcha => app(HCaptchaVerifier::class),
            self::Recaptcha => app(RecaptchaVerifier::class),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Turnstile => 'Cloudflare Turnstile',
            self::HCaptcha => 'hCaptcha',
            self::Recaptcha => 'Google reCAPTCHA',
        };
    }

    /**
     * Public site key: the admin setting "{provider}_site_key", falling back to config/services.php.
     */
    public function siteKey(): string
    {
        return (string) Settings::get($this->value.'_site_key', config('services.'.$this->value.'.site_key'));
    }

    /**
     * Secret key: the admin setting "{provider}_secret_key", falling back to config/services.php.
     */
    public function secretKey(): string
    {
        return (string) Settings::get($this->value.'_secret_key', config('services.'.$this->value.'.secret_key'));
    }

    /**
     * Widget script loaded in explicit render mode; all three expose render(element, {sitekey, callback}).
     */
    public function scriptUrl(): string
    {
        return match ($this) {
            self::Turnstile => 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
            self::HCaptcha => 'https://js.hcaptcha.com/1/api.js?render=explicit',
            self::Recaptcha => 'https://www.google.com/recaptcha/api.js?render=explicit',
        };
    }

    /**
     * Name of the global JavaScript object the widget script defines.
     */
    public function scriptGlobal(): string
    {
        return match ($this) {
            self::Turnstile => 'turnstile',
            self::HCaptcha => 'hcaptcha',
            self::Recaptcha => 'grecaptcha',
        };
    }
}
