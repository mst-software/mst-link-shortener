<?php

namespace App\Support;

use App\Services\Settings;

/**
 * Applies the mail settings saved on the admin Email settings page to config('mail.*').
 * Until that page has been saved once, the .env mail settings stay in effect.
 */
class MailConfigurator
{
    /**
     * Mailers an operator can choose from.
     *
     * @var array<string, string>
     */
    public const MAILERS = [
        'smtp' => 'SMTP server (sends real emails)',
        'log' => 'Do not send (write emails to the log file)',
    ];

    /**
     * Connection encryption options.
     *
     * @var array<string, string>
     */
    public const ENCRYPTIONS = [
        'tls' => 'TLS / STARTTLS (usually port 587)',
        'ssl' => 'SSL (usually port 465)',
        'none' => 'None (usually port 25, not recommended)',
    ];

    /**
     * Whether the Email settings page has been saved.
     */
    public static function isConfigured(): bool
    {
        return Settings::has('mail_mailer');
    }

    public static function applyFromSettings(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        $encryption = Settings::string('mail_encryption', 'tls');

        config([
            'mail.default' => Settings::string('mail_mailer', 'log'),
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => Settings::string('mail_host'),
            'mail.mailers.smtp.port' => Settings::int('mail_port'),
            'mail.mailers.smtp.username' => Settings::string('mail_username') ?: null,
            'mail.mailers.smtp.password' => Settings::string('mail_password') ?: null,
            'mail.mailers.smtp.auto_tls' => $encryption !== 'none',
            'mail.mailers.smtp.require_tls' => $encryption === 'tls',
            'mail.from.address' => Settings::string('mail_from_address'),
            'mail.from.name' => Settings::string('mail_from_name') ?: config('app.name'),
        ]);
    }
}
