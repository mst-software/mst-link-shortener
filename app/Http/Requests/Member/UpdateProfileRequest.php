<?php

namespace App\Http\Requests\Member;

use App\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Payout detail fields required for each payout method.
     *
     * @var array<string, list<string>>
     */
    public const METHOD_FIELDS = [
        'paypal' => ['paypal_email'],
        'bank_transfer' => ['bank_name', 'account_number', 'account_holder', 'branch', 'bank_phone_number'],
        'momo' => ['momo_phone'],
        'usdt' => ['usdt_address'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'fullname' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', Rule::in(array_keys(config('countries')))],
            'traffic_source' => ['nullable', 'string', 'max:1000'],
            'default_level' => ['required', 'integer', 'in:1,2,3'],
            'payout_method' => ['nullable', Rule::enum(PayoutMethod::class)],
            'paypal_email' => ['nullable', 'string', 'email', 'max:254'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'account_holder' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'bank_phone_number' => ['nullable', 'string', 'max:32'],
            'momo_phone' => ['nullable', 'string', 'max:32'],
            'usdt_address' => ['nullable', 'string', 'max:128'],
        ];

        foreach (self::METHOD_FIELDS as $method => $fields) {
            foreach ($fields as $field) {
                $rules[$field] = [Rule::requiredIf(fn (): bool => $this->input('payout_method') === $method), ...$rules[$field]];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fullname' => 'name',
            'phone_number' => 'phone',
            'paypal_email' => 'PayPal email',
            'bank_name' => 'bank name',
            'account_number' => 'account number',
            'account_holder' => 'account holder',
            'bank_phone_number' => 'bank contact phone number',
            'momo_phone' => 'Momo phone number',
            'usdt_address' => 'USDT address',
            'default_level' => 'default level',
        ];
    }

    /**
     * The payout details for the selected method only, or null when no method is chosen.
     *
     * @return array<string, string>|null
     */
    public function payoutDetails(): ?array
    {
        $method = $this->validated('payout_method');

        if (! $method) {
            return null;
        }

        $details = [];

        foreach (self::METHOD_FIELDS[$method] as $field) {
            $details[$field === 'bank_phone_number' ? 'phone_number' : $field] = (string) $this->validated($field);
        }

        return $details;
    }
}
