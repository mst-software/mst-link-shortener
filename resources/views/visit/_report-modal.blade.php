<dialog id="report-dialog" class="visit-dialog" aria-labelledby="report-title">
  <form id="report-form" class="visit-dialog-box" method="dialog">
    <div class="visit-dialog-head">
      <h2 id="report-title">Report an error</h2>
      <button type="button" class="visit-dialog-x" aria-label="Close" data-report-close>&times;</button>
    </div>

    <div class="visit-dialog-body">
      <fieldset class="visit-reasons">
        <legend>What went wrong?</legend>
        <label><input type="radio" name="choice" value="code_not_shown" checked> Code not shown</label>
        <label><input type="radio" name="choice" value="page_not_shown"> Page not shown</label>
        <label><input type="radio" name="choice" value="other"> Other</label>
      </fieldset>

      <label for="report-detail" class="visit-detail-label">Details (optional)</label>
      <textarea id="report-detail" name="detail" rows="3" maxlength="1000" placeholder="Tell us what happened"></textarea>

      <div id="report-captcha" class="visit-captcha visit-captcha-report"></div>

      <p id="report-message" class="visit-message" role="status" aria-live="polite"></p>
    </div>

    <div class="visit-dialog-actions">
      <button type="button" class="visit-dialog-cancel" data-report-close>Close</button>
      <button type="submit" class="visit-dialog-submit">Send</button>
    </div>
  </form>
</dialog>
