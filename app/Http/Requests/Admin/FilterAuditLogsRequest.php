<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminAction;
use App\Enums\TimeRange;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdmin();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', Rule::enum(AdminAction::class)],
            'admin' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'time_range' => ['nullable', Rule::enum(TimeRange::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'admin' => 'admin username',
            'q' => 'search',
            'time_range' => 'time range',
            'from' => 'start date',
            'to' => 'end date',
        ];
    }
}
