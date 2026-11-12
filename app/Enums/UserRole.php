<?php

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Admin => 'Admin',
        };
    }
}
