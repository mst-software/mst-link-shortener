<?php

namespace App\Providers;

use App\Support\SettingsConfigurator;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use PDOException;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Apply the admin settings (site name, mail, PayPal, SePay) on top of the .env configuration.
     *
     * Skipped while the configuration is being cached, so saved secrets never end up in
     * bootstrap/cache/config.php. A missing settings or cache table (fresh install, first
     * migration, test bootstrap) leaves the .env configuration untouched.
     */
    public function boot(): void
    {
        if ($this->app->runningConsoleCommand('config:cache', 'optimize')) {
            return;
        }

        try {
            SettingsConfigurator::apply();
        } catch (QueryException|PDOException) {
            // The database is not ready yet; keep the .env configuration.
        }
    }
}
