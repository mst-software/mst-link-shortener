<?php

namespace App\Support;

/**
 * Removes secrets from error messages before they are shown or stored: the exact secret values that are
 * known, credentials in URLs (scheme://user:pass@host), key=value pairs such as password=... or token=...,
 * and Authorization / Bearer values.
 */
class SecretRedactor
{
    public const MASK = '********';

    /**
     * Secrets shorter than this are still replaced, but the replacement may hit ordinary words, so
     * forDisplay() shows a generic message instead of the redacted text.
     */
    public const MIN_SAFE_LENGTH = 4;

    /**
     * Patterns of secrets in free text, with the replacement. "{mask}" is replaced by the mask.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        '~\b([a-z][a-z0-9+.-]*://)[^\s/?#@"\']+@~i' => '$1{mask}@',
        '/\bBearer\s+[^\s"\',;)\]}<>]+/i' => 'Bearer {mask}',
        '/\b(authorization)(["\']?\s*[=:]\s*["\']?)(?:(?:Basic|Bearer|Digest|Token)\s+)?[^\s"\'&,;)\]}<>]+/i' => '$1$2{mask}',
        '/\b([a-z0-9_-]*(?:token|api[_-]?key|secret|password|passwd|pwd|signature)|key)\b(["\']?\s*[=:]\s*["\']?)[^\s"\'&,;)\]}<>]+/i' => '$1$2{mask}',
    ];

    /**
     * Replace every occurrence of the given secrets (of any length, longest first, also URL-encoded)
     * and every secret-looking pattern with the mask.
     *
     * @param  array<int, string|null>  $secrets
     */
    public static function redact(string $message, array $secrets = [], string $mask = self::MASK): string
    {
        $values = collect(self::filled($secrets))
            ->flatMap(fn (string $secret): array => [$secret, rawurlencode($secret), urlencode($secret)])
            ->unique()
            ->sortByDesc(fn (string $value): int => strlen($value))
            ->values()
            ->all();

        if ($values !== []) {
            $message = str_replace($values, $mask, $message);
        }

        $replacements = array_map(fn (string $replacement): string => str_replace('{mask}', $mask, $replacement), self::PATTERNS);

        return (string) preg_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * The redacted message, or the fallback when a secret is too short to redact without mangling
     * the message. The caller should report() the original exception.
     *
     * @param  array<int, string|null>  $secrets
     */
    public static function forDisplay(string $message, array $secrets, string $fallback): string
    {
        return self::hasShortSecret($secrets) ? $fallback : self::redact($message, $secrets);
    }

    /**
     * Whether any of the given non-empty secrets is shorter than MIN_SAFE_LENGTH.
     *
     * @param  array<int, string|null>  $secrets
     */
    public static function hasShortSecret(array $secrets): bool
    {
        foreach (self::filled($secrets) as $secret) {
            if (mb_strlen($secret) < self::MIN_SAFE_LENGTH) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|null>  $secrets
     * @return list<string>
     */
    private static function filled(array $secrets): array
    {
        return array_values(array_filter(array_map(fn (?string $secret): string => (string) $secret, $secrets), fn (string $secret): bool => $secret !== ''));
    }
}
