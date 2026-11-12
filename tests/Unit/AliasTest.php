<?php

use App\Support\Alias;

it('flags every reserved alias', function (string $alias) {
    expect(Alias::isReserved($alias))->toBeTrue();
})->with(Alias::RESERVED);

it('treats reserved aliases case-insensitively', function () {
    expect(Alias::isReserved('Admin'))->toBeTrue()
        ->and(Alias::isReserved('abcdef'))->toBeFalse();
});

it('accepts 3 to 32 characters of letters, numbers, dash and underscore', function () {
    expect(Alias::isValid('abc'))->toBeTrue()
        ->and(Alias::isValid('A_b-9'))->toBeTrue()
        ->and(Alias::isValid(str_repeat('a', 32)))->toBeTrue();
});

it('rejects aliases that are too short, too long or contain invalid characters', function (string $alias) {
    expect(Alias::isValid($alias))->toBeFalse();
})->with([
    'two characters' => 'ab',
    'thirty-three characters' => str_repeat('a', 33),
    'space' => 'ab cd',
    'slash' => 'ab/cd',
    'trailing newline' => "abc\n",
]);

it('generates base62 aliases of the requested length without duplicates', function () {
    $aliases = [];
    $mixedCase = 0;

    for ($i = 0; $i < 5000; $i++) {
        $alias = Alias::generate();
        $aliases[$alias] = true;

        expect($alias)->toMatch('/\A[A-Za-z0-9]{6}\z/');

        if (preg_match('/[A-Z]/', $alias) === 1 && preg_match('/[a-z]/', $alias) === 1) {
            $mixedCase++;
        }
    }

    expect($aliases)->toHaveCount(5000)
        // A uniform base62 draw of 6 characters has both cases about 92% of the time.
        ->and($mixedCase / 5000)->toBeGreaterThan(0.88);
});

it('honours a custom length and never returns a reserved alias', function () {
    expect(Alias::generate(9))->toHaveLength(9);

    for ($i = 0; $i < 500; $i++) {
        expect(Alias::isReserved(Alias::generate(2)))->toBeFalse();
    }
});
