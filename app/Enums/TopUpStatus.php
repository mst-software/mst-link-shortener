<?php

namespace App\Enums;

enum TopUpStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Mismatch = 'mismatch';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Mismatch => 'Needs review',
        };
    }
}
