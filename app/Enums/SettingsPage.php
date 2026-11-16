<?php

namespace App\Enums;

/**
 * The pages of the admin settings area, in the order of the settings sub-navigation.
 *
 * To add a page: add a case here, then a route named "admin.settings.{value}.edit" in
 * routes/admin-settings.php with its controller, Form Request and a view that includes
 * <x-admin.settings-nav />. The sidebar keeps its single "Settings" entry.
 */
enum SettingsPage: string
{
    case General = 'general';
    case Email = 'email';
    case Payments = 'payments';
    case Traffic = 'traffic';
    case Captcha = 'captcha';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Email => 'Email',
            self::Payments => 'Payments',
            self::Traffic => 'Traffic and earnings',
            self::Captcha => 'Captcha',
        };
    }

    /**
     * Font Awesome icon name, without the "fa-" prefix.
     */
    public function icon(): string
    {
        return match ($this) {
            self::General => 'globe',
            self::Email => 'envelope',
            self::Payments => 'credit-card',
            self::Traffic => 'chart-line',
            self::Captcha => 'shield-alt',
        };
    }

    public function routeName(): string
    {
        return 'admin.settings.'.$this->value.'.edit';
    }
}
