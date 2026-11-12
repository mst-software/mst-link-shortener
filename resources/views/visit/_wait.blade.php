{{-- Wait mode: the step indicator and the step-bound ads. The countdown is shown on #visit-button. --}}
<p id="visit-step" class="visit-step-pill" data-mode-only="wait" hidden>Step 1</p>

{{-- Step ads render hidden; visit.js shows the one for the current step. They are siblings of the button, never its parent. --}}
<x-visit.ad-slot :placement="App\Enums\AdPlacement::Step1" />
<x-visit.ad-slot :placement="App\Enums\AdPlacement::Step2" />
<x-visit.ad-slot :placement="App\Enums\AdPlacement::Step3" />
<x-visit.ad-slot :placement="App\Enums\AdPlacement::Final" />
