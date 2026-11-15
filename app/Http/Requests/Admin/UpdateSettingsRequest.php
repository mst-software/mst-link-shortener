<?php

namespace App\Http\Requests\Admin;

use App\Enums\CaptchaProvider;
use App\Enums\VisitMode;
use Closure;
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
        'code_session_seconds',
    ];

    /**
     * Hosts accepted for the tutorial video link.
     *
     * @var list<string>
     */
    public const VIDEO_HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtube-nocookie.com',
        'vimeo.com', 'www.vimeo.com', 'player.vimeo.com',
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
            'visit_mode' => ['required', Rule::in(['auto', ...array_column(VisitMode::cases(), 'value')])],
            'code_session_seconds' => ['required', 'integer', 'min:120', 'max:3600'],
            'advertiser_default_price_usd' => ['sometimes', 'required', 'numeric', 'min:0.0001', 'max:100'],
            'advertiser_min_topup_usd' => ['sometimes', 'required', 'numeric', 'min:1', 'max:10000'],
            'vnd_per_usd' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000000'],
            'sepay_bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sepay_account_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sepay_account_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tutorial_video_url' => [
                'nullable', 'string', 'max:255', 'url:https',
                fn (string $attribute, mixed $value, Closure $fail) => in_array(strtolower((string) parse_url((string) $value, PHP_URL_HOST)), self::VIDEO_HOSTS, true)
                    ? null
                    : $fail('The tutorial video link must be a YouTube or Vimeo link.'),
            ],
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
            'visit_mode' => 'visit mode',
            'code_session_seconds' => 'code session length',
            'tutorial_video_url' => 'tutorial video link',
            'advertiser_default_price_usd' => 'default price per view',
            'advertiser_min_topup_usd' => 'minimum top up',
            'vnd_per_usd' => 'exchange rate',
            'sepay_bank_name' => 'bank name',
            'sepay_account_number' => 'account number',
            'sepay_account_name' => 'account name',
        ];
    }
}
