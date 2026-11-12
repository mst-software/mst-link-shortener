<?php

namespace App\Services;

use App\Exceptions\InvalidLinkException;
use App\Models\Link;
use App\Models\User;

class TokenShortener
{
    public function __construct(private readonly LinkShortener $shortener) {}

    /**
     * Create a link for the owner of an API token. Without a level the member default level is used.
     *
     * @throws InvalidLinkException
     */
    public function shorten(?string $token, ?string $url, ?string $alias = null, ?int $level = null): Link
    {
        $user = $token ? User::query()->where('api_token', $token)->first() : null;

        if ($user === null || ! $user->isActive()) {
            throw new InvalidLinkException('Invalid API token.');
        }

        if ($url === null || trim($url) === '') {
            throw new InvalidLinkException('The URL is required.');
        }

        return $this->shortener->create($user, $url, $alias, $level);
    }
}
