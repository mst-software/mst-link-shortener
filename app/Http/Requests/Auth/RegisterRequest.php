<?php

namespace App\Http\Requests\Auth;

use App\Enums\LoginEvent;
use App\Models\UserLogin;
use App\Services\Settings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public const SIGNUP_LIMIT_MESSAGE = 'Too many accounts were created from your network in the last 24 hours. Please try again tomorrow or contact support.';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9_]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'accept' => ['accepted'],
        ];
    }

    /**
     * Limit how many accounts one IP address can create in 24 hours ("signups_per_ip_per_day", 0 = no limit).
     * The count comes from the sign-up footprints, so it covers member and advertiser sign-ups alike.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $limit = Settings::int('signups_per_ip_per_day');

                if ($limit <= 0) {
                    return;
                }

                $signups = UserLogin::query()
                    ->where('event', LoginEvent::Signup)
                    ->where('ip', (string) $this->ip())
                    ->where('created_at', '>=', now()->subDay())
                    ->count();

                if ($signups >= $limit) {
                    $validator->errors()->add('username', self::SIGNUP_LIMIT_MESSAGE);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Use only letters, numbers and underscores.',
            'accept.accepted' => 'You must accept the terms.',
        ];
    }
}
