<?php

namespace App\Enums;

enum TopUpProvider: string
{
    case Sepay = 'sepay';
    case Paypal = 'paypal';

    public function label(): string
    {
        return match ($this) {
            self::Sepay => 'Bank transfer (SePay)',
            self::Paypal => 'PayPal',
        };
    }
}
