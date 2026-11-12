<?php

namespace App\Providers;

use App\Contracts\CountryResolver;
use App\Services\Views\HeaderCountryResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CountryResolver::class, HeaderCountryResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
