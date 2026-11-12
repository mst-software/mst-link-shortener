<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdDevice;
use App\Enums\AdPlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'placement' => ['required', Rule::enum(AdPlacement::class)],
            'device' => ['required', Rule::enum(AdDevice::class)],
            'network' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:65000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'is_active' => 'active',
            'sort_order' => 'sort order',
            'code' => 'ad code',
        ];
    }
}
