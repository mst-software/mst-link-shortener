<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateTrafficSettingsRequest;
use App\Services\Advertiser\PriceGuard;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TrafficSettingController extends Controller
{
    public function edit(): View
    {
        $values = [];

        foreach (UpdateTrafficSettingsRequest::INTEGER_KEYS as $key) {
            $values[$key] = Settings::int($key);
        }

        return view('admin.settings.traffic', [
            'values' => [
                ...$values,
                'visit_mode' => Settings::string('visit_mode', 'auto'),
                'tutorial_video_url' => Settings::string('tutorial_video_url'),
                'advertiser_default_price_usd' => Money::toUsdString(Settings::int('advertiser_default_price_micro')),
                'referral_hold_on_suspicion' => Settings::bool('referral_hold_on_suspicion'),
            ],
        ]);
    }

    public function update(UpdateTrafficSettingsRequest $request, PriceGuard $priceGuard): RedirectResponse
    {
        $data = $request->validated();

        foreach (UpdateTrafficSettingsRequest::INTEGER_KEYS as $key) {
            Settings::set($key, $data[$key]);
        }

        Settings::set('visit_mode', $data['visit_mode']);
        Settings::set('tutorial_video_url', $data['tutorial_video_url'] ?? '');
        Settings::set('referral_hold_on_suspicion', $data['referral_hold_on_suspicion'] ? '1' : '0');

        $price = Money::toMicro($data['advertiser_default_price_usd']);
        Settings::set('advertiser_default_price_micro', $price);

        return redirect()->route('admin.settings.traffic.edit')
            ->with('success', 'Traffic and earnings settings saved.')
            ->with('warning', $priceGuard->warningFor($price));
    }
}
