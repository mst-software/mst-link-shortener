<?php

namespace App\Http\Requests\Admin;

use App\Enums\CaptchaProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Settings stored as plain integers, keyed as in the settings table.
     *
     * @var list<string>
     */
    public const INTEGER_KEYS = [
        'dwell_seconds',
        'unique_window_hours',
        'review_days',
        'referral_percent',
        'session_minutes',
        'view_purge_days',
        'alias_length',
    ];

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
            'dwell_seconds' => ['required', 'integer', 'min:5', 'max:60'],
            'unique_window_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'review_days' => ['required', 'integer', 'min:0', 'max:60'],
            'min_withdraw_usd' => ['required', 'numeric', 'min:1', 'max:10000'],
            'referral_percent' => ['required', 'integer', 'min:0', 'max:50'],
            'session_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'view_purge_days' => ['required', 'integer', 'min:7', 'max:365'],
            'alias_length' => ['required', 'integer', 'min:6', 'max:12'],
            'captcha_provider' => ['required', Rule::enum(CaptchaProvider::class)],
        ];

        foreach (CaptchaProvider::cases() as $provider) {
            $rules[$provider->value.'_site_key'] = ['nullable', 'string', 'max:255'];
            $rules[$provider->value.'_secret_key'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dwell_seconds' => 'seconds on each step',
            'unique_window_hours' => 'unique visitor window',
            'review_days' => 'review days',
            'min_withdraw_usd' => 'minimum withdrawal',
            'referral_percent' => 'referral commission',
            'session_minutes' => 'visit session length',
            'view_purge_days' => 'raw view retention',
            'alias_length' => 'generated alias length',
            'captcha_provider' => 'captcha provider',
        ];
    }
}
