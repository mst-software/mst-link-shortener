<?php

namespace App\Providers;

use App\Contracts\CaptchaVerifier;
use App\Contracts\CountryResolver;
use App\Enums\CaptchaProvider;
use App\Services\Views\FakeCaptchaVerifier;
use App\Services\Views\HeaderCountryResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CountryResolver::class, HeaderCountryResolver::class);

        // The provider is an admin setting, so it is resolved per use, not at boot.
        $this->app->bind(CaptchaVerifier::class, function (): CaptchaVerifier {
            if ($this->app->environment('testing')) {
                return new FakeCaptchaVerifier;
            }

            return CaptchaProvider::current()->verifier();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->query('api') ?: $request->ip()));
        RateLimiter::for('visit', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
