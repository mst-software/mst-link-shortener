<?php

namespace App\Http\Requests\Member;

use App\Services\DashboardStats;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DashboardDataRequest extends FormRequest
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
            'date' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $date = DashboardStats::parse((string) $value, 'd/m/Y');

                if ($date === null || $date->isAfter(CarbonImmutable::now('UTC'))) {
                    $fail('The date must look like 13/11/2026 and cannot be in the future.');
                }
            }],
        ];
    }

    /**
     * The first day of the month that contains the requested date.
     */
    public function month(): CarbonImmutable
    {
        return DashboardStats::parse($this->string('date')->toString(), 'd/m/Y')->startOfMonth();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'info' => $validator->errors()->first(),
        ], 422));
    }
}
