<?php

namespace App\Http\Requests\Member;

use App\Services\DashboardStats;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class MonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $month = DashboardStats::parse((string) $value, 'm/Y');

                if ($month === null || $month->isAfter(CarbonImmutable::now('UTC'))) {
                    $fail('The month must look like 11/2026 and cannot be in the future.');
                }
            }],
        ];
    }

    /**
     * The requested month, or the current month when it is missing or invalid.
     */
    public function month(): CarbonImmutable
    {
        $value = $this->query('month');
        $month = is_string($value) ? DashboardStats::parse($value, 'm/Y') : null;
        $now = CarbonImmutable::now('UTC');

        return $month !== null && ! $month->isAfter($now) ? $month : $now->startOfMonth();
    }

    /**
     * AJAX callers get a 422; HTML pages quietly fall back to the current month.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'info' => $validator->errors()->first(),
            ], 422));
        }
    }
}
