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
     * @param  list<FootprintSignal>  $signals
     * @param  list<string>  $sharedIps
     */
    public function __construct(
        public User $user,
        public array $signals,
        public array $sharedIps,
        public ?string $lastIp = null,
        public ?CarbonInterface $lastSeenAt = null,
    ) {}

    /**
     * Sum of the signal weights, capped at 100.
     */
    public function score(): int
    {
        return min(100, array_sum(array_map(fn (FootprintSignal $signal): int => $signal->weight(), $this->signals)));
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
}
