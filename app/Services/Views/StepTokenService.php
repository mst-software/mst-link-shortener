<?php

namespace App\Services\Views;

use App\Enums\TokenResult;
use App\Models\VisitSession;

/**
 * Issues and verifies signed, one-time step tokens for a visit session.
 *
 * Token format: base64url(json payload) "." base64url(HMAC-SHA256(payload, app key)).
 * The payload holds the session id, step, user-agent hash, issue time and a random nonce.
 * It deliberately contains no IP address: mobile visitors change IP between steps.
 */
class StepTokenService
{
    /**
     * Issue a token for the step, store its hash on the session and stamp step_issued_at.
     */
    public function issue(VisitSession $session, int $step): string
    {
        $payload = $this->base64UrlEncode((string) json_encode([
            'sid' => $session->getKey(),
            'step' => $step,
            'ua' => $session->ua_hash,
            'iat' => time(),
            'nonce' => bin2hex(random_bytes(16)),
        ]));

        $token = $payload.'.'.$this->base64UrlEncode($this->sign($payload));

        $session->forceFill([
            'token_hash' => hash('sha256', $token),
            'step_issued_at' => now(),
        ])->save();

        return $token;
    }

    /**
     * Verify a token for the step. A valid token is consumed: it cannot be used again.
     */
    public function verify(VisitSession $session, int $step, string $token, string $uaHash): TokenResult
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return TokenResult::Tampered;
        }

        [$payload, $signature] = $parts;
        $providedSignature = $this->base64UrlDecode($signature);

        // Re-encoding rejects non-canonical base64 (altered unused trailing bits) that decodes to the same bytes.
        if (
            $providedSignature === null
            || $this->base64UrlEncode($providedSignature) !== $signature
            || ! hash_equals($this->sign($payload), $providedSignature)
        ) {
            return TokenResult::Tampered;
        }

        $claims = json_decode($this->base64UrlDecode($payload) ?? '', true);

        if (! is_array($claims) || ($claims['sid'] ?? null) !== $session->getKey()) {
            return TokenResult::Tampered;
        }

        if (($claims['step'] ?? null) !== $step) {
            return TokenResult::WrongStep;
        }

        if (! is_string($claims['ua'] ?? null) || ! hash_equals($claims['ua'], $uaHash)) {
            return TokenResult::WrongAgent;
        }

        return $this->consume($session, $token) ? TokenResult::Valid : TokenResult::Reused;
    }

    /**
     * Atomically clear the stored hash; only the first caller holding the matching hash wins.
     */
    private function consume(VisitSession $session, string $token): bool
    {
        $consumed = VisitSession::query()
            ->whereKey($session->getKey())
            ->where('token_hash', hash('sha256', $token))
            ->update(['token_hash' => null]);

        if ($consumed === 1) {
            $session->setAttribute('token_hash', null);
            $session->syncOriginalAttribute('token_hash');
        }

        return $consumed === 1;
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->key(), true);
    }

    private function key(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            return (string) base64_decode(substr($key, 7), true);
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
