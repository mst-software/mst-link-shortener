<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Settings;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * While an admin has switched on maintenance mode (General settings page), show a "back soon" page
 * to everyone except signed-in admins. Signing in (with two-factor), password resets and the PayPal
 * return of a payment that was already made stay open. Widget and API callers get a JSON 503.
 */
class CheckMaintenanceMode
{
    use ApiResponse;

    public const MESSAGE = 'We are making some improvements and will be back soon. Thank you for your patience.';

    private const RETRY_AFTER_SECONDS = '3600';

    /**
     * Named routes that stay reachable during maintenance.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'login',
        'logout',
        'two-factor.challenge',
        'password.request',
        'password.email',
        'password.reset',
        'password.update',
        'advertiser.topups.paypal.return',
        'advertiser.topups.paypal.cancel',
    ];

    /**
     * Paths of unnamed POST twins of allowed routes (routes/web.php).
     *
     * @var list<string>
     */
    private const ALLOWED_PATHS = ['auth/signin', 'auth/two-factor'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isOn() || $this->mayPass($request)) {
            return $next($request);
        }

        if ($request->is('widget/*')) {
            return $this->unavailable(response()->json(['error' => 'maintenance'], 503), true);
        }

        if ($request->routeIs('api.*')) {
            return $this->unavailable(response()->json(['status' => 'error', 'message' => self::MESSAGE], 503), true);
        }

        if ($request->expectsJson()) {
            return $this->unavailable($this->error(self::MESSAGE, 503));
        }

        return $this->unavailable(response()->view('maintenance', ['notice' => Settings::string('maintenance_message')], 503));
    }

    /**
     * Whether maintenance mode is on. An unreachable database or cache keeps the site open.
     */
    private function isOn(): bool
    {
        try {
            return Settings::bool('maintenance_mode');
        } catch (Throwable) {
            return false;
        }
    }

    private function mayPass(Request $request): bool
    {
        if ($request->is('widget/*') && $request->isMethod('OPTIONS')) {
            return true;
        }

        if ($request->routeIs(...self::ALLOWED_ROUTES) || $request->is(...self::ALLOWED_PATHS)) {
            return true;
        }

        $user = $request->hasSession() ? $request->user() : null;

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * Add the retry hint, and for cross-site callers a CORS header so their script can read the answer.
     */
    private function unavailable(Response $response, bool $crossSite = false): Response
    {
        $response->headers->set('Retry-After', self::RETRY_AFTER_SECONDS);

        if ($crossSite) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        return $response;
    }
}
