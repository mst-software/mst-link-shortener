<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdSlotRequest;
use App\Models\AdSlot;
use App\Services\AdSlotRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AdSlotController extends Controller
{
    public function __construct(private readonly AdSlotRepository $repository) {}

    public function index(): View
    {
        return view('admin.ad-slots.index', [
            'slots' => AdSlot::query()->orderBy('placement')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.ad-slots.form', ['slot' => new AdSlot]);
    }

    public function store(StoreAdSlotRequest $request): RedirectResponse
    {
        AdSlot::query()->create($this->attributes($request));
        $this->repository->flush();

        return redirect()->route('admin.ad-slots.index')->with('success', 'Ad slot created.');
    }

    public function edit(AdSlot $adSlot): View
    {
        return view('admin.ad-slots.form', ['slot' => $adSlot]);
    }

    public function update(StoreAdSlotRequest $request, AdSlot $adSlot): RedirectResponse
    {
        $adSlot->update($this->attributes($request));
        $this->repository->flush();

        return redirect()->route('admin.ad-slots.index')->with('success', 'Ad slot updated.');
    }

    public function destroy(AdSlot $adSlot): RedirectResponse
    {
        $adSlot->delete();
        $this->repository->flush();

        return redirect()->route('admin.ad-slots.index')->with('success', 'Ad slot deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreAdSlotRequest $request): array
    {
        return [
            ...$request->safe()->except('is_active'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
        ];
    }
}
