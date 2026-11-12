<?php

namespace App\Http\Requests\Member;

use App\Traits\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLinkRequest extends FormRequest
{
    use ApiResponse;

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
            'url' => ['required', 'string', 'max:2048'],
            'alias' => ['nullable', 'string', 'max:32'],
            'ad_type' => ['nullable', 'integer', 'between:1,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'url' => 'URL',
            'ad_type' => 'level',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException($this->error(
            $validator->errors()->first(),
            422,
            $validator->errors()->toArray(),
        ));
    }
}
