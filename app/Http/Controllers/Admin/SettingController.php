<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaptchaProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettingController extends Controller
{
    public function edit(): View
    {
        $siteKeys = [];
        $hasSecret = [];

        foreach (CaptchaProvider::cases() as $provider) {
            $siteKeys[$provider->value] = (string) Settings::get($provider->value.'_site_key', '');
            $hasSecret[$provider->value] = (string) Settings::get($provider->value.'_secret_key', '') !== '';
        }

        return view('admin.settings.edit', [
            'values' => [
                'dwell_seconds' => Settings::int('dwell_seconds'),
                'unique_window_hours' => Settings::int('unique_window_hours'),
                'review_days' => Settings::int('review_days'),
                'min_withdraw_usd' => rtrim(rtrim(number_format(Settings::int('min_withdraw_micro') / Money::MICRO_PER_USD, 6, '.', ''), '0'), '.'),
                'referral_percent' => Settings::int('referral_percent'),
                'session_minutes' => Settings::int('session_minutes'),
                'view_purge_days' => Settings::int('view_purge_days'),
                'alias_length' => Settings::int('alias_length'),
            ],
            'providers' => CaptchaProvider::cases(),
            'currentProvider' => CaptchaProvider::current(),
            'siteKeys' => $siteKeys,
            'hasSecret' => $hasSecret,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach (UpdateSettingsRequest::INTEGER_KEYS as $key) {
            Settings::set($key, $data[$key]);
        }

        Settings::set('min_withdraw_micro', Money::toMicro($data['min_withdraw_usd']));
        Settings::set('captcha_provider', $data['captcha_provider']);

        foreach (CaptchaProvider::cases() as $provider) {
            Settings::set($provider->value.'_site_key', $data[$provider->value.'_site_key'] ?? '');

            // A blank secret keeps the stored one, so secrets never have to be echoed back into the form.
            $secret = $data[$provider->value.'_secret_key'] ?? null;

            if (is_string($secret) && $secret !== '') {
                Settings::set($provider->value.'_secret_key', $secret);
            }
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Settings saved.');
    }
}
