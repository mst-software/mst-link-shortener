<?php

namespace App\Http\Requests\Admin\Settings;

use App\Support\MailConfigurator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailSettingsRequest extends FormRequest
{
    /**
     * A host name such as smtp.example.com, or an IPv4 address.
     */
    private const HOST_PATTERN = '/^(?=.{1,253}$)([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'mail_mailer' => ['required', Rule::in(array_keys(MailConfigurator::MAILERS))],
            'mail_host' => ['required_if:mail_mailer,smtp', 'nullable', 'string', 'max:253', 'regex:'.self::HOST_PATTERN],
            'mail_port' => ['required_if:mail_mailer,smtp', 'nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => ['required', Rule::in(array_keys(MailConfigurator::ENCRYPTIONS))],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'clear_mail_password' => ['nullable', 'boolean'],
            'mail_from_address' => ['required', 'email', 'max:254'],
            'mail_from_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mail_host.required_if' => 'Enter the SMTP server address to send real emails.',
            'mail_host.regex' => 'Enter a server address such as smtp.example.com, without http:// or a port.',
            'mail_port.required_if' => 'Enter the SMTP port to send real emails.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mail_mailer' => 'sending method',
            'mail_host' => 'SMTP server',
            'mail_port' => 'port',
            'mail_encryption' => 'encryption',
            'mail_username' => 'username',
            'mail_password' => 'password',
            'mail_from_address' => 'sender email address',
            'mail_from_name' => 'sender name',
        ];
    }
}
