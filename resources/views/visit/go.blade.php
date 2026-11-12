@extends('layouts.visit')

@section('title', 'Untitled #'.$alias)

@section('content')
<div class="lk-container">
  <div class="lk-box-main">
    <div class="lk-box-body">

      <div
        id="visit"
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
        <div id="visit-loading" class="lk-loader" role="status" aria-label="Loading"></div>

        <h1 id="visit-title" class="lk-title" hidden data-title-wait="Your link is almost ready" data-title-keyword="Please enter the code to continue to the destination">Your link is almost ready</h1>

        {{-- Keyword mode: code field --}}
        <div class="lk-code" data-only="keyword" hidden>
          <input id="visit-code" class="lk-input" type="text" inputmode="text" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="8" placeholder="Enter code" aria-label="Enter code">
        </div>

        <div id="visit-captcha-wrap" class="lk-captcha" hidden>
          <div id="visit-captcha"></div>
        </div>

        <p id="visit-session" class="lk-session" data-only="keyword" hidden>Session time left: <span id="visit-session-time">--</span></p>

        <button type="button" id="visit-button" class="lk-btn lk-btn-success" disabled hidden>Click here to continue</button>
        <p id="visit-status" class="lk-status" role="status" aria-live="polite"></p>

        <div id="visit-stop" class="lk-stop" hidden>
          <p id="visit-stop-text"></p>
          <a id="visit-stop-action" class="lk-btn lk-btn-primary" href="{{ route('visit.start', ['alias' => $alias]) }}">Open the link again</a>
        </div>

        {{-- Keyword mode: how to get the code --}}
        <div class="lk-guide" data-only="keyword" hidden>
          <h2 class="lk-guide-title">HOW TO GET THE CODE - KEYWORD SEARCH</h2>
          <p class="lk-guide-note"><b><i>Note:</i></b> <i>please follow the guide exactly so the code is not wrong)</i></p>

          <div class="lk-guide-rules">
            <p>Use the <b>Chrome</b> browser to avoid errors</p>
            <p>Do NOT click on <b>sponsored</b> ads</p>
            <p>Do NOT use an <b>incognito</b> window</p>
          </div>

          <p class="lk-step"><b>Step 1:</b> Open a new tab and go to <b class="lk-red">google.com</b></p>
          <p class="lk-step"><b>Step 2:</b> Type the keyword <b class="lk-red" id="visit-keyword"></b>
            <button type="button" class="lk-mini" id="visit-copy-keyword">Copy</button>
            <a id="visit-google" class="lk-mini lk-mini-link" href="https://www.google.com/" target="_blank" rel="noopener noreferrer">Search on Google</a>
          </p>
          <p class="lk-step"><b>Step 3:</b> Click the website <b class="lk-red" id="visit-domain"></b> on page <b>1</b></p>
          <div class="lk-example" id="visit-example-google" hidden></div>
          <p class="lk-step"><b>Step 4:</b> Scroll to the end of the article, press the <span class="lk-getcode">GET CODE</span> button and wait the number of seconds shown</p>
          <div class="lk-example" id="visit-example-button" hidden></div>
          <p class="lk-step"><b>Step 5:</b> Then click any link on the website and come back to that spot to get the code</p>
          <div class="lk-example" id="visit-example-position" hidden></div>
          <p class="lk-step"><b>Step 6:</b> Paste the code here to go to the destination</p>

          <div class="lk-code">
            <input id="visit-code-2" class="lk-input" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="8" placeholder="Enter code" aria-label="Enter code (repeat)">
          </div>
          <button type="button" id="visit-button-2" class="lk-btn lk-btn-success">Click here to continue</button>

          <p class="lk-cant">Cannot get the code?</p>
          <button type="button" class="lk-btn lk-btn-danger lk-btn-sm" id="visit-report-open">Report an error</button>

          <div id="visit-video" hidden>
            <h3 class="lk-video-title">VIDEO GUIDE</h3>
            <div class="lk-video-frame"><iframe id="visit-video-frame" title="Video guide" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation"></iframe></div>
          </div>
        </div>

        <p class="lk-cant" data-only="wait" hidden>Something not working? <button type="button" class="lk-linkbtn" data-report-open>Report an error</button></p>
      </div>

      <div id="advertise-html-wrapper">
        <x-visit.ad-slot :placement="App\Enums\AdPlacement::Top" />
        {{-- Step bound slots stay hidden until visit.js shows the one of the current step. --}}
        <x-visit.ad-slot :placement="App\Enums\AdPlacement::Step1" />
        <x-visit.ad-slot :placement="App\Enums\AdPlacement::Step2" />
        <x-visit.ad-slot :placement="App\Enums\AdPlacement::Step3" />
        <x-visit.ad-slot :placement="App\Enums\AdPlacement::Final" />
      </div>

      <div class="lk-ad-bottom">
        <x-visit.ad-slot :placement="App\Enums\AdPlacement::Bottom" />
      </div>
    </div>
  </div>
</div>

{{-- Report an error --}}
<div id="visit-report" class="lk-modal" hidden role="dialog" aria-modal="true" aria-labelledby="report-title">
  <div class="lk-modal-dialog">
    <div class="lk-modal-content">
      <div class="lk-modal-header">
        <button type="button" class="lk-modal-x" id="visit-report-x" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 id="report-title" class="lk-modal-title">REPORT AN ERROR</h4>
      </div>
      <div class="lk-modal-body">
        <p class="lk-modal-label">Problem found</p>
        <label><input type="radio" name="report-choice" value="code_not_shown"> I cannot see the code</label>
        <label><input type="radio" name="report-choice" value="page_not_shown" checked> I cannot find or open the page</label>
        <label><input type="radio" name="report-choice" value="other"> Other problem</label>
        <div class="lk-captcha"><div id="visit-report-captcha"></div></div>
        <p id="visit-report-message" class="lk-status" role="status" aria-live="polite"></p>
      </div>
      <div class="lk-modal-footer">
        <button type="button" class="lk-btn lk-btn-default" id="visit-report-close">Close</button>
        <button type="button" class="lk-btn lk-btn-blue" id="visit-report-send" disabled>Send</button>
      </div>
    </div>
  </div>
</div>
@endsection
