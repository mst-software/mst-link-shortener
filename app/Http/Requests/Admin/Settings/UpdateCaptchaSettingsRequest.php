<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\CaptchaProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaptchaSettingsRequest extends FormRequest
{
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
            'captcha_provider' => ['required', Rule::enum(CaptchaProvider::class)],
        ];

        foreach (CaptchaProvider::cases() as $provider) {
            $rules[$provider->value.'_site_key'] = ['nullable', 'string', 'max:255'];
            $rules[$provider->value.'_secret_key'] = ['nullable', 'string', 'max:255'];
            $rules['clear_'.$provider->value.'_secret_key'] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['captcha_provider' => 'captcha provider'];

        foreach (CaptchaProvider::cases() as $provider) {
            $attributes[$provider->value.'_site_key'] = $provider->label().' site key';
            $attributes[$provider->value.'_secret_key'] = $provider->label().' secret key';
        }

        return $attributes;
    }
}
