<?php

namespace App\Services;

/**
 * Time-based one-time passwords (RFC 6238) with HMAC-SHA1, as used by authenticator apps.
 */
class Totp
{
    public const PERIOD_SECONDS = 30;

    public const DIGITS = 6;

    /**
     * How many time steps before and after the current one are still accepted.
     */
    public const WINDOW = 1;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * A new random secret (160 bits) encoded as base32.
     */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * The time step a Unix timestamp falls into.
     */
    public function timeStep(int $timestamp): int
    {
        return intdiv($timestamp, self::PERIOD_SECONDS);
    }

    /**
     * The code for a base32 secret at a Unix timestamp.
     */
    public function codeAt(string $secret, int $timestamp, int $digits = self::DIGITS): string
    {
        return $this->codeForStep($secret, $this->timeStep($timestamp), $digits);
    }

    /**
     * Check a code against the current time step and its neighbours.
     *
     * @return int|null The matched time step, or null when the code is wrong.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (preg_match('/^\d{'.self::DIGITS.'}$/', $code) !== 1) {
            return null;
        }

        $currentStep = $this->timeStep($timestamp ?? now()->getTimestamp());

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            $step = $currentStep + $offset;

            if (hash_equals($this->codeForStep($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * The otpauth:// URI that authenticator apps read from a QR code or a link.
     */
    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Dynamic truncation of HMAC-SHA1 over the 8-byte big-endian counter (RFC 4226).
     */
    private function codeForStep(string $secret, int $step, int $digits = self::DIGITS): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;

        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim(str_replace(' ', '', $encoded), '='));
        $bits = '';

        foreach (str_split($encoded) as $character) {
            $value = strpos(self::BASE32_ALPHABET, $character);

            if ($value === false) {
                continue;
            }

            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr((int) bindec($chunk));
            }
        }

        return $bytes;
    }
}
