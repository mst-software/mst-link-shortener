<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsOpen
{
    public const CLOSED_MESSAGE = 'Sign-ups are closed at the moment. Please check back later.';

    /**
     * Refuse new sign-ups while an admin has closed registration on the General settings page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Settings::bool('registration_open'), 403, self::CLOSED_MESSAGE);

        return $next($request);
    }
}
