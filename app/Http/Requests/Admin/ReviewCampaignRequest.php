<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCampaignRequest extends FormRequest
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
            'review_status' => ['required', Rule::in([ReviewStatus::Approved->value, ReviewStatus::Rejected->value])],
            'review_note' => ['required_if:review_status,rejected', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'review_status' => 'decision',
            'review_note' => 'note',
        ];
    }
}
