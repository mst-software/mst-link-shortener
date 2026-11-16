<?php

use App\Support\BrandPalette;

test('the scale has every shade from 50 to 950 with the chosen colour as 600', function () {
    $shades = BrandPalette::shades('#3366CC');

    expect(array_keys($shades))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950])
        ->and($shades[600])->toBe('#3366cc')
        ->and($shades)->each->toMatch('/^#[0-9a-f]{6}$/');
});

test('shades get darker from 50 to 950', function (string $color) {
    $luminances = array_map(BrandPalette::luminance(...), array_values(BrandPalette::shades($color)));

    foreach (array_slice($luminances, 1) as $index => $luminance) {
        expect($luminance)->toBeLessThan($luminances[$index]);
    }
})->with(['#038018', '#3366cc', '#e11d48', '#facc15']);

test('the default green gives a scale close to the bundled tokens', function () {
    $shades = BrandPalette::shades('#038018');

    expect(BrandPalette::contrast($shades[50], '#effaf1'))->toBeLessThan(1.1)
        ->and(BrandPalette::contrast($shades[950], '#01240a'))->toBeLessThan(1.3);
});

test('the darkest shades stay dark enough for white text even for a light colour', function () {
    $shades = BrandPalette::shades('#fde047');

    expect(BrandPalette::contrast($shades[900], '#ffffff'))->toBeGreaterThan(4.5)
        ->and(BrandPalette::contrast($shades[950], '#ffffff'))->toBeGreaterThan(7);
});

test('white text is used on dark colours and dark text on light colours', function (string $color, bool $expectsWhite) {
    $text = BrandPalette::textColor($color);

    expect($text === '#ffffff')->toBe($expectsWhite)
        ->and(BrandPalette::contrast($color, $text))->toBeGreaterThan(4.5);
})->with([
    'default green' => ['#038018', true],
    'navy' => ['#1e3a8a', true],
    'yellow' => ['#facc15', false],
    'light green' => ['#4ade80', false],
    'white' => ['#ffffff', false],
]);

test('the css overrides the brand variables of the tokens', function () {
    $css = BrandPalette::css('#3366cc');

    expect($css)->toStartWith(':root{')
        ->toContain('--color-brand-600:#3366cc;')
        ->toContain('--color-g:#3366cc;')
        ->toContain('--color-brand-contrast:#ffffff;')
        ->toContain('--color-brand-50:')
        ->toContain('--color-brand-950:');
});

test('invalid colours are refused', function (string $color) {
    BrandPalette::shades($color);
})->throws(InvalidArgumentException::class)->with(['038018', '#03801', '#03801g', 'red', '#038018;}body{display:none']);
