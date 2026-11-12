<?php

namespace App\Enums;

enum VisitMode: string
{
    case Wait = 'wait';
    case Keyword = 'keyword';

    public function label(): string
    {
        return match ($this) {
            self::Wait => 'Wait',
            self::Keyword => 'Keyword',
        };
    }
}
