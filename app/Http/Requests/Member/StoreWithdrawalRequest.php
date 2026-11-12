<?php

namespace App\Http\Requests\Member;

use App\Models\User;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'traffic_source' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Business checks. The balance is checked again under a row lock when the money is reserved.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var User $user */
                $user = $this->user();

                if ($user->payout_method === null || empty($user->payout_details)) {
                    $validator->errors()->add('amount', 'Set your payment method and details in your profile before withdrawing.');

                    return;
                }

                $minimum = Settings::int('min_withdraw_micro');

                if ($this->amountMicro() < $minimum) {
                    $validator->errors()->add('amount', 'The minimum withdrawal is '.Money::format($minimum, 2).'.');
                } elseif ($this->amountMicro() > $user->balance_micro) {
                    $validator->errors()->add('amount', 'The amount is more than your available balance.');
                }
            },
        ];
    }

    /**
     * The requested amount in micro-USD.
     */
    public function amountMicro(): int
    {
        return Money::toMicro($this->input('amount'));
    }
}
