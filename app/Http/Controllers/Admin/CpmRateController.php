<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCpmRateRequest;
use App\Models\CpmRate;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CpmRateController extends Controller
{
    public function index(): View
    {
        $rows = CpmRate::query()
            ->orderByRaw("case when country = 'XX' then 1 else 0 end")
            ->orderBy('country')
            ->get()
            ->groupBy('country')
            ->map(fn ($rates): array => $rates->pluck('rate_per_1000_micro', 'level')->all());

        return view('admin.rates.index', ['rows' => $rows]);
    }

    public function store(StoreCpmRateRequest $request): RedirectResponse
    {
        $this->save($request);

        return redirect()->route('admin.rates.index')->with('success', 'Rates saved.');
    }

    public function update(StoreCpmRateRequest $request, string $country): RedirectResponse
    {
        $this->save($request);

        return redirect()->route('admin.rates.index')->with('success', 'Rates updated.');
    }

    public function destroy(string $country): RedirectResponse
    {
        $country = strtoupper($country);

        if ($country === 'XX') {
            return redirect()->route('admin.rates.index')->with('error', 'The fallback rate for all other countries cannot be deleted.');
        }

        CpmRate::query()->where('country', $country)->delete();

        return redirect()->route('admin.rates.index')->with('success', 'Rates deleted.');
    }

    /**
     * Upsert the three level rows of one country.
     */
    private function save(StoreCpmRateRequest $request): void
    {
        $country = $request->validated('country');

        foreach ([1, 2, 3] as $level) {
            CpmRate::query()->updateOrCreate(
                ['country' => $country, 'level' => $level],
                ['rate_per_1000_micro' => Money::toMicro($request->validated('level_'.$level))],
            );
        }
    }
}
