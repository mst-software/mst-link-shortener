<?php

namespace App\Services;

use App\Enums\LinkStatus;
use App\Exceptions\InvalidLinkException;
use App\Models\Link;
use App\Models\User;
use App\Support\Alias;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

class LinkShortener
{
    private const MAX_URL_LENGTH = 2048;

    private const MAX_GENERATED_LENGTH = 12;

    private const ATTEMPTS_PER_LENGTH = 5;

    /**
     * Create a short link for the member, optionally protected by a password or with an expiry date.
     *
     * @throws InvalidLinkException
     */
    public function create(
        User $user,
        string $url,
        ?string $alias,
        ?int $level = null,
        ?string $password = null,
        ?CarbonInterface $expiresAt = null,
    ): Link {
        $url = trim($url);
        $level ??= (int) $user->default_level;

        $this->assertValidUrl($url);

        if ($level < 1 || $level > 3) {
            throw new InvalidLinkException('The level must be 1, 2 or 3.');
        }

        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new InvalidLinkException('The expiry date must be in the future.');
        }

        $options = [
            'password' => $password !== null && $password !== '' ? $password : null,
            'expires_at' => $expiresAt,
        ];

        if ($alias !== null && $alias !== '') {
            return $this->createWithCustomAlias($user, $url, $alias, $level, $options);
        }

        return $this->createWithGeneratedAlias($user, $url, $level, $options);
    }

    /**
     * @param  array{password: ?string, expires_at: ?CarbonInterface}  $options
     */
    private function createWithCustomAlias(User $user, string $url, string $alias, int $level, array $options): Link
    {
        if (! Alias::isValid($alias)) {
            throw new InvalidLinkException('The alias must be 3 to 32 characters: letters, numbers, "-" or "_".');
        }

        if (Alias::isReserved($alias)) {
            throw new InvalidLinkException('This alias is reserved. Please choose another one.');
        }

        try {
            return $this->store($user, $url, $alias, $level, $options);
        } catch (UniqueConstraintViolationException) {
            throw new InvalidLinkException('This alias is already taken.');
        }
    }

    /**
     * @param  array{password: ?string, expires_at: ?CarbonInterface}  $options
     */
    private function createWithGeneratedAlias(User $user, string $url, int $level, array $options): Link
    {
        $startLength = max(1, Settings::int('alias_length'));

        for ($length = $startLength; $length <= max($startLength, self::MAX_GENERATED_LENGTH); $length++) {
            for ($attempt = 0; $attempt < self::ATTEMPTS_PER_LENGTH; $attempt++) {
                try {
                    return $this->store($user, $url, Alias::generate($length), $level, $options);
                } catch (UniqueConstraintViolationException) {
                    continue;
                }
            }
        }

        throw new InvalidLinkException('Could not generate a unique alias. Please try again.');
    }

    /**
     * @param  array{password: ?string, expires_at: ?CarbonInterface}  $options
     */
    private function store(User $user, string $url, string $alias, int $level, array $options): Link
    {
        return Link::query()->create([
            'user_id' => $user->id,
            'alias' => $alias,
            'url' => $url,
            'level' => $level,
            'status' => LinkStatus::Active,
            ...$options,
        ]);
    }

    /**
     * @throws InvalidLinkException
     */
    private function assertValidUrl(string $url): void
    {
        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH) {
            throw new InvalidLinkException('The URL must be between 1 and 2048 characters.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidLinkException('The URL must start with http:// or https://.');
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($appHost !== '' && ($host === $appHost || $host === 'www.'.$appHost)) {
            throw new InvalidLinkException('You cannot shorten a link that points to this site.');
        }
    }
}
