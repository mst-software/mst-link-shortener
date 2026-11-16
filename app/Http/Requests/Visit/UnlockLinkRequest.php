<?php

namespace App\Http\Requests\Visit;

use App\Services\Views\VisitFlow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class UnlockLinkRequest extends FormRequest
{
    /**
     * Any visitor may try the password of a protected link.
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
            'password' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * Check the password against the link. An unknown alias is left to the controller, which answers 404.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $link = app(VisitFlow::class)->findActiveLink((string) $this->route('alias'));

                if ($link?->isPasswordProtected() && ! Hash::check($this->string('password')->toString(), $link->password)) {
                    $validator->errors()->add('password', 'The password is incorrect.');
                }
            },
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
            'password.required' => 'Please enter the password of this link.',
        ];
    }

    /**
     * Send the visitor back to the password form of the link.
     */
    protected function getRedirectUrl(): string
    {
        return route('visit.show', ['alias' => (string) $this->route('alias')]);
    }
}
