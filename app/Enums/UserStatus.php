<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Banned => 'Banned',
        };
    }
}
