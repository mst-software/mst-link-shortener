<?php

namespace App\Enums;

enum VisitStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
        };
    }
}
