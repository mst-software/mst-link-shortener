<?php

namespace App\Enums;

enum AdPlacement: string
{
    case Step1 = 'step1';
    case Step2 = 'step2';
    case Step3 = 'step3';
    case Final = 'final';
    case Top = 'top';
    case Bottom = 'bottom';

    public function label(): string
    {
        return match ($this) {
            self::Step1 => 'Step 1',
            self::Step2 => 'Step 2',
            self::Step3 => 'Step 3',
            self::Final => 'Final step (captcha)',
            self::Top => 'Top of the page',
            self::Bottom => 'Bottom of the page',
        };
    }

    /**
     * Whether the ad is only shown while the visitor is on a given step, so the page renders it hidden.
     */
    public function isStepBound(): bool
    {
        return in_array($this, [self::Step1, self::Step2, self::Step3, self::Final], true);
    }
}
