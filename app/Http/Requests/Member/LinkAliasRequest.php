<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class LinkAliasRequest extends FormRequest
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
            'alias' => ['required', 'string', 'max:32'],
        ];
    }
}
