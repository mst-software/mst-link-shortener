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
        's3_key' => 'filesystems.disks.s3.key',
        's3_secret' => 'filesystems.disks.s3.secret',
        's3_region' => 'filesystems.disks.s3.region',
        's3_bucket' => 'filesystems.disks.s3.bucket',
        's3_endpoint' => 'filesystems.disks.s3.endpoint',
        's3_url' => 'filesystems.disks.s3.url',
    ];

    public static function apply(): void
    {
        foreach (self::CONFIG_OVERRIDES as $setting => $configKey) {
            $value = Settings::string($setting);

            if ($value !== '') {
                config([$configKey => $value]);
            }
        }

        if (Settings::has('s3_use_path_style')) {
            config(['filesystems.disks.s3.use_path_style_endpoint' => Settings::bool('s3_use_path_style')]);
        }

        MailConfigurator::applyFromSettings();
    }
}
