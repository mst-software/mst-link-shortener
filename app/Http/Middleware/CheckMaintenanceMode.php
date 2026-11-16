<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Settings;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin has switched on maintenance mode (General settings page), show a "back soon" page
 * to everyone except admins. The sign-in and sign-out routes stay open so an admin can get in.
 */
class CheckMaintenanceMode
{
    use ApiResponse;

    public const MESSAGE = 'We are making some improvements and will be back soon. Thank you for your patience.';

    /**
     * Routes that stay reachable during maintenance.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['login', 'logout'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isOn() || $this->mayPass($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return $this->error(self::MESSAGE, 503)->header('Retry-After', '3600');
        }

        return response()
            ->view('maintenance', ['notice' => Settings::string('maintenance_message')], 503)
            ->header('Retry-After', '3600');
    }

    private function isOn(): bool
    {
        try {
            return Settings::bool('maintenance_mode');
        } catch (QueryException) {
            return false;
        }
    }

    private function mayPass(Request $request): bool
    {
        $user = $request->user();

        // The sign-in form posts to the unnamed twin of the "login" route (routes/web.php), so match its path too.
        return ($user instanceof User && $user->isAdmin())
            || $request->routeIs(...self::ALLOWED_ROUTES)
            || $request->is('auth/signin');
    }
}
