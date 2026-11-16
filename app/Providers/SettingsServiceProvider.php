<?php

namespace App\Providers;

use App\Support\SettingsConfigurator;
use Illuminate\Support\ServiceProvider;
use Throwable;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Console commands that print or cache the configuration. They must show the .env configuration,
     * and saved secrets must never end up in bootstrap/cache/config.php.
     *
     * @var list<string>
     */
    public const SKIPPED_COMMANDS = ['config:cache', 'config:show', 'optimize', 'about'];

    /**
     * Apply the admin settings (site name, mail, PayPal, SePay) on top of the .env configuration.
     *
     * When the settings cannot be read (fresh install, first migration, test bootstrap, database or
     * cache unreachable) the .env configuration stays in effect, so no request, queue job or console
     * command fails because of it.
     */
    public function boot(): void
    {
        if ($this->app->runningConsoleCommand(...self::SKIPPED_COMMANDS)) {
            return;
        }

        try {
            SettingsConfigurator::apply();
        } catch (Throwable) {
            // Keep the .env configuration.
        }
    }
}
