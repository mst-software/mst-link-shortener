<?php

namespace App\Http\Requests\Visit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StepInfoRequest extends FormRequest
{
    /**
     * Visitors are anonymous; the visit session and step token are checked by the flow.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'max:32'],
            'token' => ['nullable', 'string', 'max:512'],
            'fingerprint' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9]{16,64}\z/'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fingerprint' => 'browser fingerprint',
        ];
    }
}
