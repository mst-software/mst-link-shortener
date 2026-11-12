<?php

namespace App\Http\Requests\Widget;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProgressRequest extends FormRequest
{
    /**
     * The widget runs on advertiser sites; the WidgetCors middleware checks campaign and origin.
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
            'attempt_token' => ['required', 'string', 'max:128'],
            'page_load_id' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{8,64}\z/'],
        ];
    }
}
