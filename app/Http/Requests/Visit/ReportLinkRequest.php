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
            'choice' => ['required_without:reason', 'nullable', 'string', Rule::in(self::REASONS)],
            // Older clients send "reason" instead of "choice".
            'reason' => ['required_without:choice', 'nullable', 'string', Rule::in(self::REASONS)],
            'captcha_response' => ['required', 'string', 'max:4096'],
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
            'reason.required_without' => 'Please choose what went wrong.',
            'reason.in' => 'Please choose what went wrong.',
            'choice.required_without' => 'Please choose what went wrong.',
            'choice.in' => 'Please choose what went wrong.',
            'captcha_response.required' => 'Please verify that you are human.',
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
            'captcha_response' => 'captcha',
        ];
    }
}
