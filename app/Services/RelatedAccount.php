<?php

namespace App\Services;

use App\Enums\FootprintSignal;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Another account that shares sign-up or sign-in footprints with the inspected user.
 */
final readonly class RelatedAccount
{
    public const HIGH_SCORE = 70;

    public const MEDIUM_SCORE = 40;

    /**
     * Share of a signal's weight that counts when it only comes from a shared network or a common browser.
     */
    public const SHARED_WEIGHT_PERCENT = 25;

    /**
     * @param  list<FootprintSignal>  $signals
     * @param  list<string>  $sharedIps
     * @param  list<FootprintSignal>  $weakSignals  Signals found only through values used by many accounts.
     */
    public function __construct(
        public User $user,
        public array $signals,
        public array $sharedIps,
        public ?string $lastIp = null,
        public ?CarbonInterface $lastSeenAt = null,
        public array $weakSignals = [],
    ) {}

    /**
     * Sum of the signal weights, capped at 100. A weak signal counts SHARED_WEIGHT_PERCENT of its weight.
     */
    public function score(): int
    {
        $points = array_map(
            fn (FootprintSignal $signal): int => $this->isWeak($signal) ? intdiv($signal->weight() * self::SHARED_WEIGHT_PERCENT, 100) : $signal->weight(),
            $this->signals,
        );

        return min(100, array_sum($points));
    }

    /**
     * "high", "medium" or "low".
     */
    public function level(): string
    {
        return match (true) {
            $this->score() >= self::HIGH_SCORE => 'high',
            $this->score() >= self::MEDIUM_SCORE => 'medium',
            default => 'low',
        };
    }

    public function hasSignal(FootprintSignal $signal): bool
    {
        return in_array($signal, $this->signals, true);
    }

    /**
     * Whether the signal only comes from a shared network or a common browser.
     */
    public function isWeak(FootprintSignal $signal): bool
    {
        return in_array($signal, $this->weakSignals, true);
    }
}
