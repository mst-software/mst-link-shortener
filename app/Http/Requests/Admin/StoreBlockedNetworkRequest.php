<?php

namespace App\Http\Requests\Admin;

use App\Models\BlockedNetwork;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreBlockedNetworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cidr'))) {
            $this->merge(['cidr' => trim($this->input('cidr'))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'label' => ['nullable', 'string', 'max:255'],
        ];

        // The CIDR is only entered when adding; editing changes the label only.
        if (! $this->isMethod('PUT') && ! $this->isMethod('PATCH')) {
            $rules['cidr'] = [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! BlockedNetwork::isValidCidr($value)) {
                        $fail('The :attribute must be an IPv4 or IPv6 address or CIDR range, for example 203.0.113.0/24.');
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cidr' => 'network',
        ];
    }
}
