<?php

namespace App\Support;

use App\Services\Settings;

/**
 * Overrides config values with what operators saved in the admin settings pages. A setting that is
 * empty or was never saved leaves the value from .env / config files in place.
 */
class SettingsConfigurator
{
    /**
     * Settings that override a config value one to one when they are filled.
     *
     * @var array<string, string>
     */
    public const CONFIG_OVERRIDES = [
        'site_name' => 'app.name',
        'paypal_client_id' => 'services.paypal.client_id',
        'paypal_secret' => 'services.paypal.secret',
        'paypal_mode' => 'services.paypal.mode',
        'sepay_api_token' => 'services.sepay.api_token',
        'sepay_base_url' => 'services.sepay.base_url',
    ];

    public static function apply(): void
    {
        foreach (self::CONFIG_OVERRIDES as $setting => $configKey) {
            $value = Settings::string($setting);

            if ($value !== '') {
                config([$configKey => $value]);
            }
        }

        MailConfigurator::applyFromSettings();
    }
}
