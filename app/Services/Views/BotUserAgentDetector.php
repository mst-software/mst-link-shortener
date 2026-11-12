<?php

namespace App\Services\Views;

class BotUserAgentDetector
{
    /**
     * @var list<string>
     */
    private const SIGNATURES = [
        'bot', 'crawl', 'spider', 'headless', 'curl', 'python-requests', 'phantomjs', 'selenium',
    ];

    /**
     * Whether the user agent is empty or looks like an automated client.
     */
    public function isBot(?string $userAgent): bool
    {
        $userAgent = strtolower(trim((string) $userAgent));

        if ($userAgent === '') {
            return true;
        }

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($userAgent, $signature)) {
                return true;
            }
        }

        return false;
    }
}
