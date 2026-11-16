<?php

use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The authenticator code for the user's two-factor secret at the current (test) time.
 */
function totpCode(User $user): string
{
    return (new Totp)->codeAt((string) $user->two_factor_secret, now()->getTimestamp());
}

/**
 * A well-formed six-digit code that is not accepted for the user's secret at the current (test) time.
 */
function wrongTotpCode(User $user): string
{
    $totp = new Totp;
    $candidate = 0;

    do {
        $code = str_pad((string) $candidate++, Totp::DIGITS, '0', STR_PAD_LEFT);
    } while ($totp->verify((string) $user->two_factor_secret, $code) !== null);

    return $code;
}
