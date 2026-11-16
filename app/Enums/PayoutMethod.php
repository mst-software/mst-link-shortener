<?php

namespace App\Enums;

use App\Services\Settings;

enum PayoutMethod: string
{
    case Paypal = 'paypal';
    case BankTransfer = 'bank_transfer';
    case Momo = 'momo';
    case Usdt = 'usdt';

    /**
     * Methods switched on in the "payout_methods" setting (comma separated), in declaration order.
     * Every method is offered when the setting names no known method.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        $values = explode(',', Settings::string('payout_methods'));
        $enabled = array_values(array_filter(self::cases(), fn (self $method): bool => in_array($method->value, $values, true)));

        return $enabled === [] ? self::cases() : $enabled;
    }

    public function isEnabled(): bool
    {
        return in_array($this, self::enabled(), true);
    }

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
