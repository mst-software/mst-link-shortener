<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds browser hardening headers to every response. Pages may be framed by this site only, so other
 * sites cannot overlay the visit pages (the advertiser widget is a script, not a frame). HSTS is sent
 * only over HTTPS in production, so local and staging setups on plain HTTP keep working.
 * A header a response already set is left as it is.
 */
class SecurityHeaders
{
    /**
     * @var array<string, string>
     */
    public const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), usb=()',
    ];

    public const HSTS = 'max-age=31536000';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = self::HEADERS;

        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = self::HSTS;
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
