<?php

namespace App\Services;

use App\Enums\AdDevice;
use App\Enums\AdPlacement;
use App\Models\AdSlot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class AdSlotRepository
{
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Active slots of a placement for a device class, in display order.
     * A slot set to "all" devices is always included. Cached until an admin saves a slot.
     *
     * @return Collection<int, AdSlot>
     */
    public function forPlacement(AdPlacement $placement, AdDevice $device): Collection
    {
        return Cache::remember(
            $this->cacheKey($placement, $device),
            self::CACHE_TTL_SECONDS,
            fn (): Collection => AdSlot::query()
                ->where('placement', $placement)
                ->where('is_active', true)
                ->whereIn('device', array_unique([AdDevice::All->value, $device->value]))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        );
    }

    /**
     * Drop every cached placement list. Call after an admin creates, edits or deletes a slot.
     */
    public function flush(): void
    {
        foreach (AdPlacement::cases() as $placement) {
            foreach (AdDevice::cases() as $device) {
                Cache::forget($this->cacheKey($placement, $device));
            }
        }
    }

    private function cacheKey(AdPlacement $placement, AdDevice $device): string
    {
        return 'ad_slots.'.$placement->value.'.'.$device->value;
    }
}
