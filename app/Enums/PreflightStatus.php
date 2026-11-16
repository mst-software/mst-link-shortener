<?php

namespace App\Enums;

enum PreflightStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'PASS',
            self::Warn => 'WARN',
            self::Fail => 'FAIL',
        };
    }

    /**
     * Console colour of the label.
     */
    public function colour(): string
    {
        return match ($this) {
            self::Pass => 'green',
            self::Warn => 'yellow',
            self::Fail => 'red',
        };
    }
}
