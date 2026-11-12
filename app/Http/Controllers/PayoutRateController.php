<?php

namespace App\Http\Controllers;

use App\Models\CpmRate;
use Illuminate\Contracts\View\View;
use Locale;

class PayoutRateController extends Controller
{
    public function index(): View
    {
        return view('payout-rates', ['rates' => $this->rates()]);
    }

    /**
     * One row per country with the USD rate per 1,000 views for levels 1 to 3, the fallback row last.
     *
     * @return list<array{string, float, float, float}>
     */
    private function rates(): array
    {
        $rows = CpmRate::query()
            ->get()
            ->groupBy('country')
            ->map(function ($levels, string $country): array {
                $perLevel = $levels->pluck('rate_per_1000_micro', 'level');

                return [
                    $this->countryName($country),
                    ($perLevel[1] ?? 0) / 1_000_000,
                    ($perLevel[2] ?? 0) / 1_000_000,
                    ($perLevel[3] ?? 0) / 1_000_000,
                    $country === 'XX' ? 1 : 0,
                ];
            })
            ->sort(fn (array $a, array $b): int => [$a[4], $a[0]] <=> [$b[4], $b[0]])
            ->values();

        return $rows->map(fn (array $row): array => array_slice($row, 0, 4))->all();
    }

    private function countryName(string $code): string
    {
        if ($code === 'XX') {
            return 'All other countries';
        }

        if (class_exists(Locale::class)) {
            $name = Locale::getDisplayRegion('-'.$code, 'en');

            if ($name !== '' && $name !== $code) {
                return $name;
            }
        }

        return $code;
    }
}
