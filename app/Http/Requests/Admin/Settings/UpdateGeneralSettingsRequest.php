<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\SocialNetwork;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
{
    /**
     * Line breaks, tabs and other control characters, which a single-line value must not contain.
     */
    public const CONTROL_CHARACTERS = '/[\x00-\x1F\x7F]/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'site_name' => ['required', 'string', 'max:60', 'not_regex:'.self::CONTROL_CHARACTERS],
            'support_email' => ['nullable', 'email', 'max:254'],
            'registration_open' => ['required', 'boolean'],
            'maintenance_mode' => ['required', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
        ];

        foreach (SocialNetwork::cases() as $network) {
            $rules[$network->settingKey()] = ['nullable', 'string', 'max:255', 'url:http,https'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_name.not_regex' => 'The site name must be a single line without tabs or line breaks.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'site_name' => 'site name',
            'support_email' => 'support email',
            'registration_open' => 'sign-ups',
            'maintenance_mode' => 'maintenance mode',
            'maintenance_message' => 'notice message',
        ];

        foreach (SocialNetwork::cases() as $network) {
            $attributes[$network->settingKey()] = $network->label().' link';
        }

        return $attributes;
    }
}
