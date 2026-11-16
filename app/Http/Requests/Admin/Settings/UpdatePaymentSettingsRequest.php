<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentSettingsRequest extends FormRequest
{
    /**
     * Write-only settings of this page.
     *
     * @var list<string>
     */
    public const SECRET_KEYS = ['paypal_secret', 'sepay_api_token'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'min_withdraw_usd' => ['required', 'numeric', 'min:1', 'max:10000'],
            'payout_methods' => ['required', 'array', 'min:1'],
            'payout_methods.*' => ['required', 'distinct', Rule::enum(PayoutMethod::class)],
            'advertiser_min_topup_usd' => ['required', 'numeric', 'min:1', 'max:10000'],
            'vnd_per_usd' => ['required', 'integer', 'min:1', 'max:1000000'],
            'paypal_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_secret' => ['nullable', 'string', 'max:255'],
            'clear_paypal_secret' => ['nullable', 'boolean'],
            'paypal_mode' => ['required', Rule::in(['sandbox', 'live'])],
            'sepay_api_token' => ['nullable', 'string', 'max:255'],
            'clear_sepay_api_token' => ['nullable', 'boolean'],
            'sepay_base_url' => ['nullable', 'string', 'max:255', 'url:https'],
            'sepay_bank_name' => ['nullable', 'string', 'max:100'],
            'sepay_account_number' => ['nullable', 'string', 'max:50'],
            'sepay_account_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payout_methods.required' => 'Keep at least one payout method switched on.',
            'payout_methods.min' => 'Keep at least one payout method switched on.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'min_withdraw_usd' => 'minimum withdrawal',
            'payout_methods' => 'payout methods',
            'payout_methods.*' => 'payout method',
            'advertiser_min_topup_usd' => 'minimum top up',
            'vnd_per_usd' => 'exchange rate',
            'paypal_client_id' => 'PayPal client ID',
            'paypal_secret' => 'PayPal secret',
            'paypal_mode' => 'PayPal mode',
            'sepay_api_token' => 'SePay API token',
            'sepay_base_url' => 'SePay API address',
            'sepay_bank_name' => 'bank name',
            'sepay_account_number' => 'account number',
            'sepay_account_name' => 'account name',
        ];
    }
}
