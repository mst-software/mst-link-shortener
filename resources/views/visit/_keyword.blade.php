{{-- Keyword mode guide. visit.js fills [data-kw] text, the campaign images and the video from /links/get-link-info. --}}
<section id="keyword-guide" class="visit-card visit-guide" data-mode-only="keyword" hidden>
  <h2 class="visit-guide-title">How to get the code - keyword search</h2>
  <p class="visit-guide-note"><strong>Note:</strong> please follow the guide exactly so you get the right code.</p>

  <ul class="visit-tips">
    <li>Use the <strong>Chrome browser</strong> to avoid errors</li>
    <li><strong>Do not</strong> click on <strong>sponsored ads</strong></li>
    <li><strong>Do not</strong> use an <strong>incognito</strong> window</li>
  </ul>

  <ol class="visit-steps">
    <li class="visit-step">
      <span class="visit-step-num" aria-hidden="true">1</span>
      <div class="visit-step-body">
        <p><strong class="visit-step-label">Step 1:</strong> Open a new tab and go to <a href="https://www.google.com" target="_blank" rel="noopener noreferrer" class="visit-hl">google.com</a></p>
      </div>
    </li>

    <li class="visit-step">
      <span class="visit-step-num" aria-hidden="true">2</span>
      <div class="visit-step-body">
        <p><strong class="visit-step-label">Step 2:</strong> Search for the keyword <strong class="visit-hl" data-kw="keyword"></strong></p>
        <div class="visit-search-mock">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <span class="visit-search-text" data-kw="keyword"></span>
          <button type="button" class="visit-copy" data-copy-keyword>Copy</button>
        </div>
      </div>
    </li>

    <li class="visit-step">
      <span class="visit-step-num" aria-hidden="true">3</span>
      <div class="visit-step-body">
        <p><strong class="visit-step-label">Step 3:</strong> Click the website <strong class="visit-hl" data-kw="domain"></strong> on page <strong class="visit-hl">1</strong> of the results</p>
        <figure class="visit-shot" data-shot="google">
          <img alt="The advertiser website in the search results" hidden>
          <div class="visit-shot-default visit-mock-result">
            <div class="visit-mock-site">
              <span class="visit-mock-favicon" aria-hidden="true"></span>
              <div>
                <div class="visit-mock-name" data-kw="domain"></div>
                <div class="visit-mock-url">https://<span data-kw="domain"></span></div>
              </div>
            </div>
            <div class="visit-mock-title"><span data-kw="keyword"></span> - a complete guide</div>
            <div class="visit-mock-line"></div>
            <div class="visit-mock-line visit-mock-line-short"></div>
          </div>
        </figure>
      </div>
    </li>

    <li class="visit-step">
      <span class="visit-step-num" aria-hidden="true">4</span>
      <div class="visit-step-body">
        <p><strong class="visit-step-label">Step 4:</strong> Scroll to the end of the article, click the <span class="visit-chip">GET CODE</span> button as shown below and wait for the timer</p>
        <figure class="visit-shot" data-shot="button">
          <img alt="The GET CODE button at the end of the article" hidden>
          <div class="visit-shot-default visit-mock-article">
            <div class="visit-mock-line"></div>
            <div class="visit-mock-line"></div>
            <div class="visit-mock-line visit-mock-line-short"></div>
            <div class="visit-mock-cta">
              <svg class="visit-mock-arrow" viewBox="0 0 40 40" aria-hidden="true"><path d="M20 2v30M8 21l12 13 12-13"/></svg>
              <span class="visit-chip visit-chip-lg">GET CODE</span>
            </div>
          </div>
        </figure>
      </div>
    </li>

    <li class="visit-step">
      <span class="visit-step-num" aria-hidden="true">5</span>
      <div class="visit-step-body">
        <p><strong class="visit-step-label">Step 5:</strong> Then click any link on the website and come back to the same position to get the code</p>
        <figure class="visit-shot" data-shot="position">
          <img alt="Click any link, then scroll back to the code box" hidden>
          <div class="visit-shot-default visit-mock-site-nav">
            <div class="visit-mock-nav">
              <span class="visit-mock-nav-item is-target">Home</span>
              <span class="visit-mock-nav-item">Guides</span>
              <span class="visit-mock-nav-item">Tips</span>
              <span class="visit-mock-nav-item">About</span>
            </div>
            <p class="visit-mock-hint">Click any link, then scroll back to the <span class="visit-chip">GET CODE</span> box</p>
          </div>
        </figure>
      </div>
    </li>

    <li class="visit-step">
      <span class="visit-step-num" aria-hidden="true">6</span>
      <div class="visit-step-body">
        <p><strong class="visit-step-label">Step 6:</strong> Paste the code into the box at the top of this page to go to the destination</p>
        <button type="button" class="visit-jump" data-code-jump>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
          Enter the code
        </button>
      </div>
    </li>
  </ol>

  <div class="visit-help">
    <p>Can't get the code?</p>
    <button type="button" class="visit-report-button" data-report-open>Report an error</button>
  </div>

  <div id="keyword-video" class="visit-video" hidden>
    <h2 class="visit-guide-title">Video: how to get the code</h2>
    <div class="visit-video-frame">
      <iframe
        id="keyword-video-frame"
        title="How to get the code"
        loading="lazy"
        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
        referrerpolicy="strict-origin-when-cross-origin"
        allowfullscreen
      ></iframe>
    </div>
  </div>
</section>
