<?php

namespace App\Http\Requests\Auth;

use App\Services\Settings;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public const SIGNUP_LIMIT_MESSAGE = 'We cannot create a new account from this network right now. Please try again later.';

    public const SIGNUP_WINDOW_SECONDS = 86_400;

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
     * Limit how many accounts one network can create in 24 hours ("signups_per_ip_per_day", 0 = no limit).
     * Member and advertiser sign-ups share the count.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $limit = Settings::int('signups_per_ip_per_day');

                if ($limit > 0 && RateLimiter::attempts($this->signupLimiterKey()) >= $limit) {
                    $validator->errors()->add('username', self::SIGNUP_LIMIT_MESSAGE);
                }
            },
        ];
    }

    /**
     * Count a successful sign-up against the network's daily limit.
     */
    public function countSignup(): void
    {
        RateLimiter::hit($this->signupLimiterKey(), self::SIGNUP_WINDOW_SECONDS);
    }

    /**
     * The network a sign-up is counted against: the address itself for IPv4, the /64 prefix for IPv6
     * (one home or phone usually gets a whole /64, so single IPv6 addresses are cheap to rotate).
     */
    public static function signupNetwork(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        $packed = inet_pton($ip);

        return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
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

    private function signupLimiterKey(): string
    {
        return 'signups:'.self::signupNetwork((string) $this->ip());
    }
}
