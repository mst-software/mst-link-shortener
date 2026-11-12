<?php

namespace App\Services\Visits;

enum CodeResult: string
{
    case Accepted = 'accepted';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case WrongDevice = 'wrong_device';
    case Locked = 'locked';

    /**
     * Error key returned to the visitor page for a refused code.
     */
    public function errorKey(): string
    {
        return match ($this) {
            self::Accepted => 'accepted',
            self::Invalid => 'invalid_code',
            self::Expired => 'expired_code',
            self::WrongDevice => 'wrong_device',
            self::Locked => 'locked',
        };
    }

    /**
     * Message shown to the visitor when the code is refused.
     */
    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'OK',
            self::Invalid => 'This code is not correct. Please check it and try again.',
            self::Expired => 'This code has expired. Please get a new code.',
            self::WrongDevice => 'This code was issued to another browser. Please get the code with this browser.',
            self::Locked => 'Too many wrong codes. Please open the link again.',
        };
    }
}
