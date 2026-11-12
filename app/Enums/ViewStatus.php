<?php

namespace App\Enums;

enum ViewStatus: string
{
    case Pending = 'pending';
    case Released = 'released';
    case Cancelled = 'cancelled';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Released => 'Released',
            self::Cancelled => 'Cancelled',
            self::Invalid => 'Invalid',
        };
    }
}
