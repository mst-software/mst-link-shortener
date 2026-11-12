<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class ReferralRedirectController extends Controller
{
    private const COOKIE_MINUTES = 60 * 24 * 30;

    public function __invoke(string $code): RedirectResponse
    {
        $response = redirect()->route('register');

        if (preg_match('/^[A-Za-z0-9]{7}$/', $code) === 1) {
            $response->withCookie(Cookie::make('ref', $code, self::COOKIE_MINUTES));
        }

        return $response;
    }
}
