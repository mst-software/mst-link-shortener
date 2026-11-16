<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureRegistrationIsOpen;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsAdvertiser;
use App\Http\Middleware\EnsureUserIsNotAdvertiser;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/member.php',
            __DIR__.'/../routes/advertiser.php',
            __DIR__.'/../routes/admin.php',
            __DIR__.'/../routes/widget.php',
            __DIR__.'/../routes/web.php',
        ],
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Which proxies are trusted comes from TRUSTED_PROXIES (config/trustedproxy.php), read per request.
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->validateCsrfTokens(except: ['widget/*']);
        $middleware->web(append: [CheckMaintenanceMode::class]);
        $middleware->api(append: [CheckMaintenanceMode::class]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
            'advertiser' => EnsureUserIsAdvertiser::class,
            'not-advertiser' => EnsureUserIsNotAdvertiser::class,
            'registration-open' => EnsureRegistrationIsOpen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
