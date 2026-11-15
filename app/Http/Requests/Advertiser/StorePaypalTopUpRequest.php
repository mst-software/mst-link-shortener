<?php

namespace App\Http\Requests\Advertiser;

use App\Services\Settings;
use Illuminate\Foundation\Http\FormRequest;

class StorePaypalTopUpRequest extends FormRequest
{
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
            'usd' => ['required', 'numeric', 'decimal:0,2', 'min:'.(Settings::int('advertiser_min_topup_micro') / 1_000_000), 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['usd' => 'amount'];
    }

    /**
     * The amount in micro-USD.
     */
    public function micro(): int
    {
        return (int) round($this->float('usd') * 1_000_000);
    }
}
