<?php

use App\Support\Money;

it('converts a decimal string of USD to micro-USD', function (): void {
    expect(Money::toMicro('0.05'))->toBe(50000)
        ->and(Money::toMicro('4.5'))->toBe(4500000);
});

it('converts int and float USD to micro-USD', function (): void {
    expect(Money::toMicro(5))->toBe(5000000)
        ->and(Money::toMicro(0.05))->toBe(50000);
});

it('formats micro-USD with three decimals by default', function (): void {
    expect(Money::format(50000))->toBe('$0.050')
        ->and(Money::format(0))->toBe('$0.000')
        ->and(Money::format(4500000))->toBe('$4.500');
});

it('formats micro-USD with a custom number of decimals', function (): void {
    expect(Money::format(5000000, 2))->toBe('$5.00');
});
