@extends('layouts.visit')

@section('title', 'Untitled #'.$alias)

@section('content')
<div class="unlock-shell">
  <x-visit.ad-slot :placement="App\Enums\AdPlacement::Top" />

  <div class="unlock-grid">
    <div class="unlock-station-col">
      {{-- The unlock station serves both modes. visit.js reads "mode" from /links/get-link-info and shows the matching [data-mode-only] blocks. --}}
      <section
        id="visit"
        class="station"
        tabindex="-1"
        aria-labelledby="visit-title"
        data-mode="loading"
        data-alias="{{ $alias }}"
        data-info-url="{{ route('visit.info') }}"
        data-finish-url="{{ route('visit.check-captcha') }}"
        data-start-url="{{ route('visit.start', ['alias' => $alias]) }}"
        data-report-url="{{ route('visit.report') }}"
        data-home-url="{{ route('home') }}"
        data-captcha-provider="{{ $captchaProvider->value }}"
        data-captcha-site-key="{{ $captchaProvider->siteKey() }}"
        data-captcha-script="{{ $captchaProvider->scriptUrl() }}"
        data-captcha-global="{{ $captchaProvider->scriptGlobal() }}"
        @if ($preview) data-preview="{{ json_encode($preview) }}" @endif
      >
        <header class="station-head">
          <p class="station-eyebrow">@include('visit._icon', ['name' => 'lock']) Unlock your link</p>
          <h1
            id="visit-title"
            class="station-title"
            data-title-wait="Your link is almost ready"
            data-title-keyword="Enter the code to continue"
          >Your link is almost ready</h1>
          <p class="station-lede" data-mode-only="keyword" hidden>Get a 6-character code from our partner site, type it below and you are through.</p>
          <p class="station-lede" data-mode-only="wait" hidden>Hang tight while the timer runs, then press Continue.</p>
        </header>

        <div class="station-live">
          <div id="visit-dial" class="dial" data-state="loading">
            <svg class="dial-ring" viewBox="0 0 120 120" aria-hidden="true" focusable="false">
              <circle class="dial-track" cx="60" cy="60" r="52" />
              <circle id="visit-ring" class="dial-progress" cx="60" cy="60" r="52" />
            </svg>
            <div class="dial-center" aria-hidden="true">
              <span id="visit-dial-value" class="dial-value">&middot;&middot;</span>
              <span class="dial-check">@include('visit._icon', ['name' => 'check'])</span>
              <span id="visit-dial-label" class="dial-label">Loading</span>
            </div>
          </div>

          <ol id="visit-dots" class="dial-dots" aria-hidden="true"></ol>
          <p id="visit-step" class="dial-caption" aria-live="polite"></p>

          <p id="visit-session" class="dial-session" data-mode-only="keyword" hidden>
            @include('visit._icon', ['name' => 'clock'])
            <span><span id="visit-session-label">Session time left:</span> <span id="visit-session-seconds">--:--</span></span>
          </p>
          <p id="visit-announcer" class="visually-hidden" aria-live="polite"></p>
        </div>

        <div class="station-controls">
          <div id="visit-quick-check" class="quick-check" hidden>
            <p class="quick-check-title">@include('visit._icon', ['name' => 'shield']) Quick check</p>
            <div id="visit-captcha" class="captcha-box"></div>
          </div>

          {{-- On phones in keyword mode this dock sticks to the bottom of the screen; on wider screens its children flow inside the card. --}}
          <div id="visit-dock" class="dock">
            <div class="code-field" data-mode-only="keyword" hidden>
              <div class="code-field-head">
                <label id="visit-code-label" for="visit-code-1" class="code-field-label">Your 6-character code</label>
                <button type="button" class="code-paste" data-code-paste hidden>@include('visit._icon', ['name' => 'clipboard']) Paste</button>
              </div>
              <div id="visit-code" class="code-cells" role="group" aria-labelledby="visit-code-label" aria-describedby="visit-message">
                @for ($i = 1; $i <= 6; $i++)
                  <input
                    id="visit-code-{{ $i }}"
                    class="code-cell"
                    type="text"
                    inputmode="text"
                    autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}"
                    autocapitalize="none"
                    autocorrect="off"
                    spellcheck="false"
                    enterkeyhint="{{ $i === 6 ? 'go' : 'next' }}"
                    aria-label="Character {{ $i }} of 6"
                    data-code-cell="{{ $i - 1 }}"
                  >
                @endfor
              </div>
            </div>

            <div id="visit-message" class="notice" role="status" aria-live="polite" hidden>
              <span class="notice-icon">@include('visit._icon', ['name' => 'alert', 'class' => 'notice-alert'])@include('visit._icon', ['name' => 'check', 'class' => 'notice-ok'])</span>
              <span class="notice-text"></span>
            </div>

            <button id="visit-button" type="button" class="unlock-button" disabled>
              <span class="unlock-button-spinner" aria-hidden="true"></span>
              <span class="unlock-button-label">Please wait&hellip;</span>
              @include('visit._icon', ['name' => 'arrow-right', 'class' => 'unlock-button-arrow'])
            </button>

            <p id="visit-status" class="status-line" data-tone="pending" aria-live="polite">
              <span class="status-icon status-icon-pending" aria-hidden="true"></span>
              @include('visit._icon', ['name' => 'check', 'class' => 'status-icon status-icon-ready'])
              <span class="status-text">Loading&hellip;</span>
            </p>
          </div>
        </div>

        {{-- Replaces the controls when the visitor cannot go on (session over, too many wrong codes, link gone). --}}
        <div id="visit-stop" class="stop-panel" data-icon="clock" hidden>
          <span class="stop-icon">
            @include('visit._icon', ['name' => 'clock', 'class' => 'stop-icon-clock'])
            @include('visit._icon', ['name' => 'lock', 'class' => 'stop-icon-lock'])
            @include('visit._icon', ['name' => 'alert', 'class' => 'stop-icon-alert'])
          </span>
          <h2 id="visit-stop-title" class="stop-title" tabindex="-1"></h2>
          <p id="visit-stop-text" class="stop-text"></p>
          <a id="visit-stop-action" class="unlock-button is-ready" href="{{ route('visit.start', ['alias' => $alias]) }}">
            @include('visit._icon', ['name' => 'refresh'])
            <span class="unlock-button-label">Start again</span>
          </a>
        </div>

        <noscript>
          <p class="notice is-error">Please enable JavaScript in your browser to continue.</p>
        </noscript>

        <p class="station-help">
          <button type="button" class="link-button" data-report-open>
            @include('visit._icon', ['name' => 'flag'])
            Something wrong? Report it
          </button>
        </p>
      </section>

      @include('visit._wait')
    </div>

    @include('visit._keyword')
  </div>

  <x-visit.ad-slot :placement="App\Enums\AdPlacement::Bottom" />
</div>

<section class="earn-strip" aria-labelledby="earn-title">
  <div class="unlock-shell earn-strip-inner">
    <div class="earn-copy">
      <img src="{{ Vite::asset('resources/images/logo-icon.png') }}" alt="" width="44" height="44" class="earn-mark">
      <div>
        <p class="earn-kicker">Earn from your own links</p>
        <h2 id="earn-title" class="earn-title">Ready to earn money?</h2>
      </div>
    </div>
    <div class="earn-actions">
      <a href="{{ route('register') }}" class="btn-ink">Sign up free</a>
      <a href="{{ route('login') }}" class="btn-outline">Sign in</a>
    </div>
  </div>
</section>

@include('visit._report-modal')
@endsection
