{{-- "Report an error": a bottom sheet on phones, a centred dialog on wider screens. --}}
<dialog id="report-dialog" class="sheet" aria-labelledby="report-title" aria-describedby="report-intro">
  <form id="report-form" class="sheet-box" method="dialog">
    <span class="sheet-grabber" aria-hidden="true"></span>

    <div class="sheet-head">
      <div>
        <h2 id="report-title" class="sheet-title">Report an error</h2>
        <p id="report-intro" class="sheet-intro">Tell us what went wrong so we can fix it.</p>
      </div>
      <button type="button" class="icon-button" aria-label="Close" data-report-close>@include('visit._icon', ['name' => 'close'])</button>
    </div>

    <fieldset class="choices">
      <legend class="visually-hidden">What went wrong?</legend>
      <label class="choice">
        <input type="radio" name="choice" value="code_not_shown" checked>
        <span class="choice-card">
          <span class="choice-icon">@include('visit._icon', ['name' => 'code-off'])</span>
          <span class="choice-copy"><span class="choice-title">Code not shown</span><span class="choice-desc">GET CODE or the code never appeared</span></span>
          <span class="choice-tick">@include('visit._icon', ['name' => 'check'])</span>
        </span>
      </label>
      <label class="choice">
        <input type="radio" name="choice" value="page_not_shown">
        <span class="choice-card">
          <span class="choice-icon">@include('visit._icon', ['name' => 'page-off'])</span>
          <span class="choice-copy"><span class="choice-title">Page not shown</span><span class="choice-desc">The site or the article did not load</span></span>
          <span class="choice-tick">@include('visit._icon', ['name' => 'check'])</span>
        </span>
      </label>
      <label class="choice">
        <input type="radio" name="choice" value="other">
        <span class="choice-card">
          <span class="choice-icon">@include('visit._icon', ['name' => 'message'])</span>
          <span class="choice-copy"><span class="choice-title">Other</span><span class="choice-desc">Something else went wrong</span></span>
          <span class="choice-tick">@include('visit._icon', ['name' => 'check'])</span>
        </span>
      </label>
    </fieldset>

    <label for="report-detail" class="field-label">Details <span class="field-optional">(optional)</span></label>
    <textarea id="report-detail" class="field-textarea" name="detail" rows="3" maxlength="1000" placeholder="What did you see?"></textarea>

    <div class="quick-check quick-check-report">
      <p class="quick-check-title">@include('visit._icon', ['name' => 'shield']) Quick check</p>
      <div id="report-captcha" class="captcha-box"></div>
    </div>

    <div id="report-message" class="notice" role="status" aria-live="polite" hidden>
      <span class="notice-icon">@include('visit._icon', ['name' => 'alert', 'class' => 'notice-alert'])@include('visit._icon', ['name' => 'check', 'class' => 'notice-ok'])</span>
      <span class="notice-text"></span>
    </div>

    <div class="sheet-actions">
      <button type="button" class="btn-quiet" data-report-close>Cancel</button>
      <button type="submit" class="btn-ink">Send report</button>
    </div>
  </form>
</dialog>
