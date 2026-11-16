<?php

namespace App\Http\Requests\Member;

use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Re-confirms the account owner with the current password and a two-factor code,
 * before two-factor is turned off or the recovery codes are replaced.
 */
class VerifyTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'code' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'current password',
            'code' => 'authentication code',
        ];
    }

    /**
     * The code is checked last, so a wrong password does not use up a recovery code.
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

                /** @var User $user */
                $user = $this->user();

                if (! app(TwoFactorAuthentication::class)->verify($user, (string) $this->input('code'))) {
                    $validator->errors()->add('code', 'The authentication code is invalid.');
                }
            },
        ];
    }
}
