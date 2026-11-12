<?php

namespace App\Http\Requests\Visit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckCaptchaRequest extends FormRequest
{
    /**
     * Visitors are anonymous; the step token identifies the visit session.
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
            // Required for a wait mode session; a keyword session sends the code instead.
            'token' => ['nullable', 'string', 'max:512'],
            'code' => ['nullable', 'string', 'max:32'],
            'fingerprint' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9]{16,64}\z/'],
            'captcha_response' => ['nullable', 'string', 'max:4096'],
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
            'captcha_response' => 'captcha',
        ];
    }
}
