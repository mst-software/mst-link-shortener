<?php

namespace App\Http\Requests\Member;

use App\Services\DashboardStats;
use Illuminate\Validation\Rule;

/**
 * The statistic page: the month of the daily report plus the day range of the view breakdowns.
 */
class StatisticRequest extends MonthRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'range' => ['nullable', 'integer', Rule::in(DashboardStats::BREAKDOWN_RANGES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'range' => 'The range must be one of: '.implode(', ', DashboardStats::BREAKDOWN_RANGES).' days.',
        ];
    }

    /**
     * The requested breakdown range in days, or the default range when it is missing or invalid.
     */
    public function range(): int
    {
        $value = $this->query('range');

        foreach (DashboardStats::BREAKDOWN_RANGES as $days) {
            if ($value === (string) $days) {
                return $days;
            }
        }

        return DashboardStats::DEFAULT_BREAKDOWN_RANGE;
    }
}
