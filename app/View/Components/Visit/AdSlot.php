<?php

namespace App\View\Components\Visit;

use App\Enums\AdDevice;
use App\Enums\AdPlacement;
use App\Models\AdSlot as AdSlotModel;
use App\Services\AdSlotRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

class AdSlot extends Component
{
    public AdPlacement $placement;

    /**
     * @param  AdPlacement|string  $placement  A placement or its value, for example "top".
     */
    public function __construct(AdPlacement|string $placement, private readonly AdSlotRepository $repository)
    {
        $this->placement = $placement instanceof AdPlacement ? $placement : AdPlacement::from($placement);
    }

    /**
     * Active slots of this placement for the visitor's device class.
     *
     * @return Collection<int, AdSlotModel>
     */
    public function activeSlots(): Collection
    {
        return $this->repository->forPlacement($this->placement, AdDevice::fromUserAgent(request()->userAgent()));
    }

    public function render(): View
    {
        return view('components.visit.ad-slot');
    }
}
