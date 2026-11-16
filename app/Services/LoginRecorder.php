<?php

namespace App\Services;

use App\Contracts\CountryResolver;
use App\Enums\LoginEvent;
use App\Models\User;
use App\Models\UserLogin;
use App\Services\Views\VisitorContext;
use Illuminate\Http\Request;

/**
 * Writes one sign-up or sign-in footprint per successful sign in, for the multi-account checks.
 * Only the IP address, a SHA-256 hash of the user agent, the user agent trimmed to 255 characters
 * and the country are stored.
 */
class LoginRecorder
{
    public function __construct(private readonly Request $request, private readonly CountryResolver $countries) {}

    /**
     * Record a footprint with a single insert. A failure is reported but never blocks the sign in.
     */
    public function record(User $user, LoginEvent $event): void
    {
        rescue(function () use ($user, $event): void {
            $visitor = VisitorContext::fromRequest($this->request, $this->countries);

            UserLogin::query()->create([
                'user_id' => $user->getKey(),
                'event' => $event,
                'ip' => $visitor->ip,
                'ua_hash' => $visitor->uaHash,
                'user_agent' => $visitor->userAgent === '' ? null : mb_substr($visitor->userAgent, 0, UserLogin::USER_AGENT_LENGTH),
                'country' => $visitor->country === 'XX' ? null : $visitor->country,
            ]);
        });
    }
}
