<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\CaptchaProvider;
use App\Http\Controllers\Concerns\SavesSecretSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateCaptchaSettingsRequest;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CaptchaSettingController extends Controller
{
    use SavesSecretSettings;

    public function edit(): View
    {
        $siteKeys = [];
        $hasSecret = [];

        foreach (CaptchaProvider::cases() as $provider) {
            $siteKeys[$provider->value] = Settings::string($provider->value.'_site_key');
            $hasSecret[$provider->value] = Settings::string($provider->value.'_secret_key') !== '';
        }

        return view('admin.settings.captcha', [
            'providers' => CaptchaProvider::cases(),
            'currentProvider' => CaptchaProvider::current(),
            'siteKeys' => $siteKeys,
            'hasSecret' => $hasSecret,
        ]);
    }

    public function update(UpdateCaptchaSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Settings::set('captcha_provider', $data['captcha_provider']);

        foreach (CaptchaProvider::cases() as $provider) {
            $siteKey = (string) ($data[$provider->value.'_site_key'] ?? '');

            // A blank site key falls back to the key in .env, like a secret that was never saved.
            if ($siteKey === '') {
                Settings::forget($provider->value.'_site_key');
            } else {
                Settings::set($provider->value.'_site_key', $siteKey);
            }
        }

        $this->saveSecretSettings($data, array_map(
            fn (CaptchaProvider $provider): string => $provider->value.'_secret_key',
            CaptchaProvider::cases(),
        ));

        return redirect()->route('admin.settings.captcha.edit')->with('success', 'Captcha settings saved.');
    }
}
