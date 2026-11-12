<?php

namespace App\Enums;

enum LinkStatus: string
{
    case Active = 'active';
    case Hidden = 'hidden';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Hidden => 'Hidden',
            self::Banned => 'Banned',
        };
    }
}
