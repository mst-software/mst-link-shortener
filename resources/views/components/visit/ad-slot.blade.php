@php($slots = $activeSlots())
@if ($slots->isNotEmpty())
  {{-- Ad code is trusted admin input and is printed unescaped by design. The container never wraps the Continue button. --}}
  <div class="ad-container ad-container-{{ $placement->value }}" data-ad-placement="{{ $placement->value }}" @if ($placement->isStepBound()) data-ad-step-bound hidden @endif>
    <p class="ad-label">Sponsored</p>
    @if ($placement->isStepBound())
      <template>
    @endif
    @foreach ($slots as $slot)
      <div class="ad-slot" data-ad-slot="{{ $slot->id }}">{!! $slot->code !!}</div>
    @endforeach
    @if ($placement->isStepBound())
      </template>
    @endif
  </div>
@endif
