<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Settings
{
    private const CACHE_KEY = 'settings.all';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Settings holding credentials. They are stored encrypted and must never be rendered back into a page.
     *
     * @var list<string>
     */
    public const SECRET_KEYS = [
        'turnstile_secret_key',
        'hcaptcha_secret_key',
        'recaptcha_secret_key',
        'mail_password',
        'paypal_secret',
        'sepay_api_token',
    ];

    /**
     * Get a setting value, falling back to config('linkcash.defaults') and then $default.
     * Secret settings are decrypted; a legacy plain-text secret is returned as it is.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $stored = self::all();

        if (array_key_exists($key, $stored)) {
            if (! self::isSecret($key)) {
                return $stored[$key];
            }

            $secret = self::decrypt($key, $stored[$key]);

            if ($secret !== null) {
                return $secret;
            }
        }

        return config('linkcash.defaults.'.$key, $default);
    }

    /**
     * Store a setting value and clear the cached table. Secret settings are encrypted first.
     */
    public static function set(string $key, mixed $value): void
    {
        $value = $value === null ? null : (string) $value;

        if ($value !== null && $value !== '' && self::isSecret($key)) {
            $value = Crypt::encryptString($value);
        }

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Remove a stored setting, so the config default (or the .env value) applies again.
     */
    public static function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Whether the settings table has a row for the key.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * Whether the key is a secret that is stored encrypted.
     */
    public static function isSecret(string $key): bool
    {
        return in_array($key, self::SECRET_KEYS, true);
    }

    /**
     * Get a setting value as an integer.
     */
    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /**
     * Get a setting value as a boolean ("1", "true", "on" and true count as yes).
     */
    public static function bool(string $key): bool
    {
        return filter_var(self::get($key), FILTER_VALIDATE_BOOL);
    }

    /**
     * Get a setting value as a string; null becomes an empty string.
     */
    public static function string(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    /**
     * Length of the keyword code session in seconds; a missing or non-positive value falls back to the default.
     */
    public static function codeSessionSeconds(): int
    {
        $seconds = self::int('code_session_seconds');

        return $seconds > 0 ? $seconds : (int) config('linkcash.defaults.code_session_seconds', 600);
    }

    /**
     * Decrypt a stored secret. Values saved before secrets were encrypted are plain text and are returned
     * unchanged. An encrypted value that no longer decrypts (for example after APP_KEY changed) counts as
     * unset (null), so the ciphertext is never used as the secret.
     */
    private static function decrypt(string $key, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            if (! self::looksEncrypted($value)) {
                return $value;
            }

            Log::warning('The saved secret setting "'.$key.'" cannot be decrypted (was APP_KEY changed?). It is treated as not set; save it again in the admin settings.');

            return null;
        }
    }

    /**
     * Whether the value has the shape of a Laravel encrypter payload: base64 JSON with iv, value and mac.
     */
    private static function looksEncrypted(string $value): bool
    {
        $payload = json_decode((string) base64_decode($value, true), true);

        return is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac']);
    }

    /**
     * @return array<string, string|null>
     */
    private static function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
