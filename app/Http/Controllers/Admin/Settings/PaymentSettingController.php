<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\PayoutMethod;
use App\Http\Controllers\Concerns\SavesSecretSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdatePaymentSettingsRequest;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PaymentSettingController extends Controller
{
    use SavesSecretSettings;

    public function edit(): View
    {
        return view('admin.settings.payments', [
            'values' => [
                'min_withdraw_usd' => Money::toUsdString(Settings::int('min_withdraw_micro')),
                'advertiser_min_topup_usd' => Money::toUsdString(Settings::int('advertiser_min_topup_micro')),
                'vnd_per_usd' => Settings::int('vnd_per_usd'),
                'paypal_client_id' => Settings::string('paypal_client_id'),
                'paypal_mode' => (string) config('services.paypal.mode', 'sandbox'),
                'sepay_base_url' => Settings::string('sepay_base_url'),
                'sepay_bank_name' => Settings::string('sepay_bank_name'),
                'sepay_account_number' => Settings::string('sepay_account_number'),
                'sepay_account_name' => Settings::string('sepay_account_name'),
            ],
            'payoutMethods' => PayoutMethod::cases(),
            'enabledMethods' => PayoutMethod::enabled(),
            'savedSecrets' => [
                'paypal_secret' => Settings::string('paypal_secret') !== '',
                'sepay_api_token' => Settings::string('sepay_api_token') !== '',
            ],
            'serverDefaults' => [
                'paypal_secret' => filled(config('services.paypal.secret')),
                'sepay_api_token' => filled(config('services.sepay.api_token')),
            ],
        ]);
    }

    public function update(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Settings::set('min_withdraw_micro', Money::toMicro($data['min_withdraw_usd']));
        Settings::set('advertiser_min_topup_micro', Money::toMicro($data['advertiser_min_topup_usd']));
        Settings::set('vnd_per_usd', $data['vnd_per_usd']);
        Settings::set('payout_methods', implode(',', array_unique($data['payout_methods'])));
        Settings::set('paypal_mode', $data['paypal_mode']);

        foreach (['paypal_client_id', 'sepay_base_url', 'sepay_bank_name', 'sepay_account_number', 'sepay_account_name'] as $key) {
            Settings::set($key, $data[$key] ?? '');
        }

        $this->saveSecretSettings($data, UpdatePaymentSettingsRequest::SECRET_KEYS);

        return redirect()->route('admin.settings.payments.edit')->with('success', 'Payment settings saved.');
    }
}
