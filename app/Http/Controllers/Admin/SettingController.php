<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CaptchaProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\Advertiser\PriceGuard;
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
                'code_session_seconds' => Settings::int('code_session_seconds'),
                'visit_mode' => (string) Settings::get('visit_mode', 'auto'),
                'tutorial_video_url' => (string) Settings::get('tutorial_video_url', ''),
                'advertiser_default_price_usd' => rtrim(rtrim(number_format(Settings::int('advertiser_default_price_micro') / Money::MICRO_PER_USD, 6, '.', ''), '0'), '.'),
                'advertiser_min_topup_usd' => rtrim(rtrim(number_format(Settings::int('advertiser_min_topup_micro') / Money::MICRO_PER_USD, 6, '.', ''), '0'), '.'),
                'vnd_per_usd' => Settings::int('vnd_per_usd'),
                'sepay_bank_name' => (string) Settings::get('sepay_bank_name', ''),
                'sepay_account_number' => (string) Settings::get('sepay_account_number', ''),
                'sepay_account_name' => (string) Settings::get('sepay_account_name', ''),
            ],
            'providers' => CaptchaProvider::cases(),
            'currentProvider' => CaptchaProvider::current(),
            'siteKeys' => $siteKeys,
            'hasSecret' => $hasSecret,
        ]);
    }

    public function update(UpdateSettingsRequest $request, PriceGuard $priceGuard): RedirectResponse
    {
        $data = $request->validated();
        $warning = null;

        foreach (UpdateSettingsRequest::INTEGER_KEYS as $key) {
            Settings::set($key, $data[$key]);
        }

        Settings::set('min_withdraw_micro', Money::toMicro($data['min_withdraw_usd']));
        Settings::set('captcha_provider', $data['captcha_provider']);
        Settings::set('visit_mode', $data['visit_mode']);
        Settings::set('tutorial_video_url', $data['tutorial_video_url'] ?? '');

        if (isset($data['advertiser_default_price_usd'])) {
            $price = Money::toMicro($data['advertiser_default_price_usd']);
            Settings::set('advertiser_default_price_micro', $price);
            $warning = $priceGuard->warningFor($price);
        }

        if (isset($data['advertiser_min_topup_usd'])) {
            Settings::set('advertiser_min_topup_micro', Money::toMicro($data['advertiser_min_topup_usd']));
        }

        if (isset($data['vnd_per_usd'])) {
            Settings::set('vnd_per_usd', $data['vnd_per_usd']);
        }

        foreach (['sepay_bank_name', 'sepay_account_number', 'sepay_account_name'] as $key) {
            if (array_key_exists($key, $data)) {
                Settings::set($key, $data[$key] ?? '');
            }
        }

        foreach (CaptchaProvider::cases() as $provider) {
            Settings::set($provider->value.'_site_key', $data[$provider->value.'_site_key'] ?? '');

            // A blank secret keeps the stored one, so secrets never have to be echoed back into the form.
            $secret = $data[$provider->value.'_secret_key'] ?? null;

            if (is_string($secret) && $secret !== '') {
                Settings::set($provider->value.'_secret_key', $secret);
            }
        }

        return redirect()->route('admin.settings.edit')
            ->with('success', 'Settings saved.')
            ->with('warning', $warning);
    }
}
