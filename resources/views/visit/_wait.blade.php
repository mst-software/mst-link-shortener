{{-- Wait mode step ads. They render hidden; visit.js shows the one for the current step. They sit below the unlock station, never around its button. --}}
<div class="step-ads" data-mode-only="wait" hidden>
  <x-visit.ad-slot :placement="App\Enums\AdPlacement::Step1" />
  <x-visit.ad-slot :placement="App\Enums\AdPlacement::Step2" />
  <x-visit.ad-slot :placement="App\Enums\AdPlacement::Step3" />
  <x-visit.ad-slot :placement="App\Enums\AdPlacement::Final" />
</div>
