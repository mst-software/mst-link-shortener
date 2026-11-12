<?php

namespace App\Http\Requests\Member;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class MassShrinkRequest extends FormRequest
{
    public const MAX_URLS = 20;

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
            'urls' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['urls' => 'links'];
    }

    /**
     * @return list<string>
     */
    public function urlLines(): array
    {
        $lines = preg_split('/\R/', (string) $this->input('urls')) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $count = count($this->urlLines());

                if ($count === 0) {
                    $validator->errors()->add('urls', 'Enter at least one URL.');
                } elseif ($count > self::MAX_URLS) {
                    $validator->errors()->add('urls', 'You can shorten at most '.self::MAX_URLS.' links at once.');
                }
            },
        ];
    }
}
