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

it('converts decimal strings exactly, without float rounding errors', function (string $usd, int $micro): void {
    expect(Money::toMicro($usd))->toBe($micro);
})->with([
    'smallest unit' => ['0.000001', 1],
    'float-unsafe value' => ['1.005', 1_005_000],
    'float-unsafe sum' => ['0.3', 300_000],
    'all six decimals' => ['99999.999999', 99_999_999_999],
    'upper admin limit' => ['100000', 100_000_000_000],
    'just above the limit' => ['100000.000001', 100_000_000_001],
    'large withdrawal' => ['1000000000', 1_000_000_000_000_000],
    'no integer part' => ['.5', 500_000],
    'trailing dot' => ['7.', 7_000_000],
    'seventh decimal rounds up' => ['0.0000005', 1],
    'seventh decimal rounds down' => ['0.0000004', 0],
    'negative' => ['-2.25', -2_250_000],
    'surrounding spaces' => [' 3.1 ', 3_100_000],
    'exponent notation' => ['1e3', 1_000_000_000],
    'not a number' => ['abc', 0],
]);

it('formats micro-USD with three decimals by default', function (): void {
    expect(Money::format(50000))->toBe('$0.050')
        ->and(Money::format(0))->toBe('$0.000')
        ->and(Money::format(4500000))->toBe('$4.500');
});

it('formats micro-USD with a custom number of decimals', function (): void {
    expect(Money::format(5000000, 2))->toBe('$5.00');
});
