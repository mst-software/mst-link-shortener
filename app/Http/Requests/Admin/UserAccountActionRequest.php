<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by the admin user actions that only take an optional note for the audit log:
 * unban, mark email verified, resend the verification email and send a password reset link.
 */
class UserAccountActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
