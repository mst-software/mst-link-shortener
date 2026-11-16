<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class AdjustUserBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'decimal:0,6', 'min:0.000001', 'max:100000'],
            'reason' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'direction' => 'credit or debit',
            'amount' => 'amount (USD)',
        ];
    }

    public function isCredit(): bool
    {
        return $this->validated('direction') === 'credit';
    }

    /**
     * The amount in micro-USD.
     */
    public function amountMicro(): int
    {
        return Money::toMicro((string) $this->validated('amount'));
    }
}
