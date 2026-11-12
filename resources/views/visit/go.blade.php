@extends('layouts.visit')

@section('title', 'Untitled #'.$alias)

@section('content')
<div class="visit-container">
  {{-- Ad slot "top" goes here (added by the ad slots task). --}}

  <section
    id="visit"
    class="visit-card"
    data-alias="{{ $alias }}"
    data-info-url="{{ route('visit.info') }}"
    data-start-url="{{ route('visit.start', ['alias' => $alias]) }}"
    data-report-url="{{ route('visit.report') }}"
    data-captcha-provider="{{ $captchaProvider->value }}"
    data-captcha-site-key="{{ $captchaProvider->siteKey() }}"
    data-captcha-script="{{ $captchaProvider->scriptUrl() }}"
    data-captcha-global="{{ $captchaProvider->scriptGlobal() }}"
  >
    <p id="visit-step" class="visit-step">Loading&hellip;</p>
    <h1 class="visit-title">Your link is almost ready</h1>

    {{-- Ad slot for the current step goes here (added by the ad slots task). --}}

    <div id="visit-countdown" class="visit-countdown" aria-live="polite" hidden>
      <span id="visit-seconds" class="visit-seconds">0</span>
      <span class="visit-seconds-label">seconds left</span>
    </div>

    <div id="visit-captcha" class="visit-captcha" hidden></div>

    <button id="visit-button" type="button" class="visit-button" disabled>Please wait&hellip;</button>

    <p id="visit-message" class="visit-message" role="status" aria-live="polite"></p>

    <noscript>
      <p class="visit-message visit-message-error">Please enable JavaScript in your browser to continue.</p>
    </noscript>

    <button type="button" class="visit-report-open" data-report-open>Report an error</button>
  </section>

  {{-- Ad slot "bottom" goes here (added by the ad slots task). --}}

  <section class="visit-cta">
    <h2>Ready to earn money?</h2>
    <p>Shorten your links, share them anywhere and get paid for every valid view.</p>
    <div class="visit-cta-actions">
      <a href="{{ route('register') }}" class="visit-cta-primary">Sign up</a>
      <a href="{{ route('login') }}" class="visit-cta-secondary">Sign in</a>
    </div>
  </section>
</div>

<dialog id="report-dialog" class="visit-dialog" aria-labelledby="report-title">
  <form id="report-form" class="visit-dialog-box" method="dialog">
    <h2 id="report-title">Report an error</h2>
    <fieldset class="visit-reasons">
      <legend>What went wrong?</legend>
      <label><input type="radio" name="reason" value="code_not_shown" checked> Code not shown</label>
      <label><input type="radio" name="reason" value="page_not_shown"> Page not shown</label>
      <label><input type="radio" name="reason" value="other"> Other</label>
    </fieldset>
    <label for="report-detail" class="visit-detail-label">Details (optional)</label>
    <textarea id="report-detail" name="detail" rows="4" maxlength="1000" placeholder="Tell us what happened"></textarea>
    <p id="report-message" class="visit-message" role="status" aria-live="polite"></p>
    <div class="visit-dialog-actions">
      <button type="button" class="visit-dialog-cancel" data-report-close>Cancel</button>
      <button type="submit" class="visit-dialog-submit">Send report</button>
    </div>
  </form>
</dialog>
@endsection
