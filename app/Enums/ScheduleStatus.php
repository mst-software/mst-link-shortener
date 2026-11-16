<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Ok = 'ok';
    case Waiting = 'waiting';
    case Late = 'late';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Waiting => 'Waiting',
            self::Late => 'Late',
            self::Failed => 'Failed',
        };
    }

    /**
     * Whether monitoring should raise an alarm for this status.
     */
    public function needsAttention(): bool
    {
        return $this === self::Late || $this === self::Failed;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Ok => 'label-success',
            self::Waiting => 'label-default',
            self::Late => 'label-warning',
            self::Failed => 'label-danger',
        };
    }
}
