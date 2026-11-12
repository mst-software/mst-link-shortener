<?php

namespace App\Http\Requests\Visit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportLinkRequest extends FormRequest
{
    /**
     * Reasons a visitor can pick in the "Report an error" dialog.
     *
     * @var list<string>
     */
    public const REASONS = ['code_not_shown', 'page_not_shown', 'other'];

    /**
     * Any visitor may report a problem with a link.
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
            'reason' => ['required', 'string', Rule::in(self::REASONS)],
            'detail' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Please choose what went wrong.',
            'reason.in' => 'Please choose what went wrong.',
        ];
    }
}
