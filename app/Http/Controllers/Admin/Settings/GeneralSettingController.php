<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\SocialNetwork;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class GeneralSettingController extends Controller
{
    public function edit(): View
    {
        $socialLinks = [];

        foreach (SocialNetwork::cases() as $network) {
            $socialLinks[$network->settingKey()] = Settings::string($network->settingKey());
        }

        return view('admin.settings.general', [
            'values' => [
                'site_name' => (string) config('app.name'),
                'support_email' => Settings::string('support_email'),
                'registration_open' => Settings::bool('registration_open'),
                'maintenance_mode' => Settings::bool('maintenance_mode'),
                'maintenance_message' => Settings::string('maintenance_message'),
                ...$socialLinks,
            ],
            'socialNetworks' => SocialNetwork::cases(),
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Settings::set('site_name', trim($data['site_name']));
        Settings::set('support_email', $data['support_email'] ?? '');
        Settings::set('registration_open', $data['registration_open'] ? '1' : '0');
        Settings::set('maintenance_mode', $data['maintenance_mode'] ? '1' : '0');
        Settings::set('maintenance_message', trim((string) ($data['maintenance_message'] ?? '')));

        foreach (SocialNetwork::cases() as $network) {
            Settings::set($network->settingKey(), $data[$network->settingKey()] ?? '');
        }

        return redirect()->route('admin.settings.general.edit')->with('success', 'General settings saved.');
    }
}
