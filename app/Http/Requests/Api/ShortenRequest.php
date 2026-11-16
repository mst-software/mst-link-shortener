<?php

namespace App\Http\Requests\Api;

use App\Rules\MaxBytes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ShortenRequest extends FormRequest
{
    /**
     * The API token is checked by the shortener, so every caller may reach it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules for the optional link options; the token, URL, alias and level are checked by the shortener.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'min:4', 'max:64', new MaxBytes(72)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'expires_at' => 'expiry date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expires_at.after' => 'The expiry date must be in the future.',
        ];
    }

    /**
     * Answer in the same shape as the other API errors, as plain text for "format=text".
     */
    protected function failedValidation(Validator $validator): void
    {
        $message = $validator->errors()->first();

        $response = $this->query('format') === 'text'
            ? response($message)
            : response()->json(['status' => 'error', 'message' => $message]);

        $response->headers->set('Access-Control-Allow-Origin', '*');

        throw new HttpResponseException($response);
    }
}
