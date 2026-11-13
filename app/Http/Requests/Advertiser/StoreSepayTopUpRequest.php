<?php

namespace App\Http\Requests\Advertiser;

use App\Services\Advertiser\TopUpService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSepayTopUpRequest extends FormRequest
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
            'vnd' => ['required', 'integer', 'min:'.app(TopUpService::class)->minimumVnd(), 'max:1000000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['vnd' => 'amount'];
    }
}
