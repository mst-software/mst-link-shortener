<?php

use App\Mail\SettingsTestMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

uses(RefreshDatabase::class);

/**
 * A valid payload for the email settings form, with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function emailSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'mail_mailer' => 'smtp',
        'mail_host' => 'smtp.example.com',
        'mail_port' => 587,
        'mail_encryption' => 'tls',
        'mail_username' => 'mailer-user',
        'mail_password' => '',
        'mail_from_address' => 'noreply@example.com',
        'mail_from_name' => 'Example Site',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
});

test('admins save the email settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.email.update'), emailSettingsPayload(['mail_password' => 'smtp-password-value']))
        ->assertRedirect(route('admin.settings.email.edit'))
        ->assertSessionHas('success');

    expect(Settings::get('mail_mailer'))->toBe('smtp')
        ->and(Settings::get('mail_host'))->toBe('smtp.example.com')
        ->and(Settings::int('mail_port'))->toBe(587)
        ->and(Settings::get('mail_encryption'))->toBe('tls')
        ->and(Settings::get('mail_username'))->toBe('mailer-user')
        ->and(Settings::get('mail_password'))->toBe('smtp-password-value')
        ->and(Setting::query()->where('key', 'mail_password')->value('value'))->not->toContain('smtp-password-value')
        ->and(Settings::get('mail_from_address'))->toBe('noreply@example.com')
        ->and(Settings::get('mail_from_name'))->toBe('Example Site');
});

test('the password is write-only and a blank password keeps the saved one', function () {
    Settings::set('mail_password', 'smtp-password-value');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.email.update'), emailSettingsPayload())
        ->assertSessionHasNoErrors();

    expect(Settings::get('mail_password'))->toBe('smtp-password-value');

    $this->actingAs($this->admin)
        ->get(route('admin.settings.email.edit'))
        ->assertOk()
        ->assertSee('Saved')
        ->assertDontSee('smtp-password-value');
});

test('invalid email settings are rejected', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.email.update'), emailSettingsPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Settings::has('mail_mailer'))->toBeFalse();
})->with([
    'unknown mailer' => [['mail_mailer' => 'sendmail'], 'mail_mailer'],
    'host with a scheme' => [['mail_host' => 'https://smtp.example.com'], 'mail_host'],
    'missing host for SMTP' => [['mail_host' => ''], 'mail_host'],
    'port too high' => [['mail_port' => 70000], 'mail_port'],
    'port zero' => [['mail_port' => 0], 'mail_port'],
    'unknown encryption' => [['mail_encryption' => 'starttls'], 'mail_encryption'],
    'invalid sender address' => [['mail_from_address' => 'not-an-email'], 'mail_from_address'],
    'sender name with a line break' => [['mail_from_name' => "Example\r\nBcc: x@example.com"], 'mail_from_name'],
    'password over 1000 characters' => [['mail_password' => str_repeat('a', 1001)], 'mail_password'],
]);

test('the log mailer needs no SMTP server', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.email.update'), emailSettingsPayload(['mail_mailer' => 'log', 'mail_host' => '', 'mail_port' => '']))
        ->assertSessionHasNoErrors();

    expect(Settings::get('mail_mailer'))->toBe('log');
});

test('the test email is sent to the signed-in admin', function () {
    Mail::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.settings.email.test'))
        ->assertRedirect(route('admin.settings.email.edit'))
        ->assertSessionHas('success', 'A test email was sent to admin@example.com. Check the inbox (and the spam folder).');

    Mail::assertSent(SettingsTestMail::class, fn (SettingsTestMail $mail): bool => $mail->hasTo('admin@example.com'));
});

test('a failing test email shows the mail server error without the password', function () {
    Settings::set('mail_mailer', 'smtp');
    Settings::set('mail_host', 'smtp.example.com');
    Settings::set('mail_port', 587);
    Settings::set('mail_encryption', 'tls');
    Settings::set('mail_password', 'smtp-password-value');
    Settings::set('mail_from_address', 'noreply@example.com');

    Mail::extend('smtp', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Failed to authenticate on SMTP server with password "smtp-password-value": 535 Authentication failed.');
        }

        public function __toString(): string
        {
            return 'failing-smtp';
        }
    });

    $response = $this->actingAs($this->admin)
        ->post(route('admin.settings.email.test'))
        ->assertRedirect(route('admin.settings.email.edit'))
        ->assertSessionHas('error');

    expect(session('error'))->toContain('The test email could not be sent: Failed to authenticate on SMTP server')
        ->toContain('535 Authentication failed.')
        ->not->toContain('smtp-password-value');

    $this->followRedirects($response)->assertDontSee('smtp-password-value');
});

/**
 * Save SMTP settings with the given password and make the SMTP transport fail with the given message.
 */
function failSmtpWith(string $password, string $message): void
{
    Settings::set('mail_mailer', 'smtp');
    Settings::set('mail_host', 'smtp.example.com');
    Settings::set('mail_port', 587);
    Settings::set('mail_password', $password);
    Settings::set('mail_from_address', 'noreply@example.com');

    Mail::extend('smtp', fn () => new class($message) extends AbstractTransport
    {
        public function __construct(private readonly string $failure)
        {
            parent::__construct();
        }

        protected function doSend(SentMessage $message): void
        {
            throw new TransportException($this->failure);
        }

        public function __toString(): string
        {
            return 'failing-smtp';
        }
    });
}

test('a failing test email is reported to the log', function () {
    Exceptions::fake();
    failSmtpWith('smtp-password-value', 'Connection refused');

    $this->actingAs($this->admin)->post(route('admin.settings.email.test'));

    Exceptions::assertReported(TransportException::class);
});

test('a very short password is not redacted from the error message', function () {
    failSmtpWith('ab', 'Unable to connect to the server.');

    $this->actingAs($this->admin)->post(route('admin.settings.email.test'));

    expect(session('error'))->toBe('The test email could not be sent: Unable to connect to the server.');
});

test('the mail server error is shown escaped', function () {
    failSmtpWith('smtp-password-value', 'Bad greeting <script>alert(1)</script>');

    $response = $this->actingAs($this->admin)->post(route('admin.settings.email.test'));

    $this->followRedirects($response)
        ->assertSee('Bad greeting &lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('members cannot send a test email', function () {
    Mail::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.settings.email.test'))
        ->assertForbidden();

    Mail::assertNothingSent();
});
