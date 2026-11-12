<?php

namespace App\Enums;

enum PayoutMethod: string
{
    case Paypal = 'paypal';
    case BankTransfer = 'bank_transfer';
    case Momo = 'momo';
    case Usdt = 'usdt';

    public function label(): string
    {
        return match ($this) {
            self::Paypal => 'PayPal',
            self::BankTransfer => 'Bank Transfer',
            self::Momo => 'Momo',
            self::Usdt => 'USDT',
        };
    }
}
