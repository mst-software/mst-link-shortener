@extends('layouts.visit')

@section('title', 'Untitled #'.$alias)

@section('content')
<div class="visit-container">
  <x-visit.ad-slot :placement="App\Enums\AdPlacement::Top" />

  {{-- One card for both modes. visit.js picks the mode from the "mode" field of /links/get-link-info and shows the matching [data-mode-only] blocks. --}}
  <section
    id="visit"
    class="visit-card visit-main-card"
    data-mode="loading"
    data-alias="{{ $alias }}"
    data-info-url="{{ route('visit.info') }}"
    data-finish-url="{{ route('visit.check-captcha') }}"
    data-start-url="{{ route('visit.start', ['alias' => $alias]) }}"
    data-report-url="{{ route('visit.report') }}"
    data-captcha-provider="{{ $captchaProvider->value }}"
    data-captcha-site-key="{{ $captchaProvider->siteKey() }}"
    data-captcha-script="{{ $captchaProvider->scriptUrl() }}"
    data-captcha-global="{{ $captchaProvider->scriptGlobal() }}"
    @if ($preview) data-preview="{{ json_encode($preview) }}" @endif
  >
    <h1
      id="visit-title"
      class="visit-title"
      data-title-wait="Your link is almost ready"
      data-title-keyword="Please enter the code to continue to the destination"
    >Your link is almost ready</h1>

    @include('visit._wait')

    <div class="visit-code-field" data-mode-only="keyword" hidden>
      <label for="visit-code" class="visually-hidden">Code</label>
      <input
        id="visit-code"
        class="visit-code-input"
        type="text"
        name="code"
        placeholder="Enter code"
        maxlength="16"
        autocomplete="off"
        autocapitalize="none"
        autocorrect="off"
        spellcheck="false"
        enterkeyhint="go"
      >
    </div>

    <div id="visit-captcha" class="visit-captcha" hidden></div>

    <p id="visit-session" class="visit-session" data-mode-only="keyword" aria-live="off" hidden>
      Session time left: <span id="visit-session-seconds">0</span>s
    </p>

    <button id="visit-button" type="button" class="visit-button" disabled>Please wait&hellip;</button>

    <p id="visit-status" class="visit-status" aria-live="polite">Loading&hellip;</p>
    <p id="visit-message" class="visit-message" role="status" aria-live="polite"></p>

    <noscript>
      <p class="visit-message visit-message-error">Please enable JavaScript in your browser to continue.</p>
    </noscript>

    <div class="visit-help visit-help-compact" data-mode-only="wait">
      <span>Something not working?</span>
      <button type="button" class="visit-report-link" data-report-open>Report an error</button>
    </div>
  </section>

  @include('visit._keyword')

  <x-visit.ad-slot :placement="App\Enums\AdPlacement::Bottom" />
</div>

<section class="visit-cta">
  <div class="visit-container">
    <h2>Ready to earn money?</h2>
    <p>Sign up for a LinkCash account and start earning from every link you shorten and share.</p>
    <div class="visit-cta-actions">
      <a href="{{ route('register') }}" class="visit-cta-primary">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M8.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19 8v6M22 11h-6"/></svg>
        Sign up
      </a>
      <a href="{{ route('login') }}" class="visit-cta-secondary">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
        Sign in
      </a>
    </div>
  </div>
</section>

@include('visit._report-modal')
@endsection
