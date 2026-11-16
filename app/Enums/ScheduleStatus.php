<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Ok = 'ok';
    case Late = 'late';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Late => 'Late',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Ok => 'label-success',
            self::Late => 'label-warning',
            self::Failed => 'label-danger',
        };
    }
}
