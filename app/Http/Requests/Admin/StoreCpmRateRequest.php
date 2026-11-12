<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCpmRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $country = $this->route('country') ?? $this->input('country');

        $this->merge(['country' => is_string($country) ? strtoupper(trim($country)) : $country]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'level_1' => ['required', 'numeric', 'min:0', 'max:1000'],
            'level_2' => ['required', 'numeric', 'min:0', 'max:1000'],
            'level_3' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country' => 'country code',
            'level_1' => 'level 1 rate',
            'level_2' => 'level 2 rate',
            'level_3' => 'level 3 rate',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country.regex' => 'The country code must be two letters (ISO 3166-1 alpha-2), or XX for all other countries.',
        ];
    }
}
