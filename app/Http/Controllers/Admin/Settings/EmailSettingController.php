<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Concerns\SavesSecretSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateEmailSettingsRequest;
use App\Mail\SettingsTestMail;
use App\Models\User;
use App\Services\Settings;
use App\Support\MailConfigurator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailSettingController extends Controller
{
    use SavesSecretSettings;

    private const MIN_REDACTED_PASSWORD_LENGTH = 4;

    public function edit(): View
    {
        $mailer = (string) config('mail.default');

        return view('admin.settings.email', [
            'values' => [
                'mail_mailer' => array_key_exists($mailer, MailConfigurator::MAILERS) ? $mailer : 'smtp',
                'mail_host' => (string) config('mail.mailers.smtp.host'),
                'mail_port' => (string) config('mail.mailers.smtp.port'),
                'mail_encryption' => MailConfigurator::isConfigured() ? Settings::string('mail_encryption', 'tls') : 'tls',
                'mail_username' => (string) config('mail.mailers.smtp.username'),
                'mail_from_address' => (string) config('mail.from.address'),
                'mail_from_name' => (string) config('mail.from.name'),
            ],
            'currentMailer' => $mailer,
            'isConfigured' => MailConfigurator::isConfigured(),
            'hasPassword' => Settings::string('mail_password') !== '',
            'hasServerPassword' => filled(config('mail.mailers.smtp.password')),
            'mailers' => MailConfigurator::MAILERS,
            'encryptions' => MailConfigurator::ENCRYPTIONS,
        ]);
    }

    public function update(UpdateEmailSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach (['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_from_address', 'mail_from_name'] as $key) {
            Settings::set($key, $data[$key] ?? '');
        }

        $this->saveSecretSettings($data, ['mail_password']);

        return redirect()->route('admin.settings.email.edit')
            ->with('success', 'Email settings saved. Use "Send test email" to check them.');
    }

    /**
     * Send a test email to the signed-in admin with the saved mail settings.
     */
    public function test(Request $request): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        MailConfigurator::applyFromSettings();
        Mail::purge();

        try {
            Mail::to($admin->email)->send(new SettingsTestMail);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.settings.email.edit')
                ->with('error', 'The test email could not be sent: '.$this->withoutPassword($exception->getMessage()));
        }

        return redirect()->route('admin.settings.email.edit')
            ->with('success', 'A test email was sent to '.$admin->email.'. Check the inbox (and the spam folder).');
    }

    /**
     * Hide the SMTP password if the mail server echoed it back in its error message. Very short
     * passwords are left alone, because replacing them would mangle ordinary words in the message.
     */
    private function withoutPassword(string $message): string
    {
        $password = (string) config('mail.mailers.smtp.password');

        return mb_strlen($password) < self::MIN_REDACTED_PASSWORD_LENGTH ? $message : str_replace($password, '********', $message);
    }
}
