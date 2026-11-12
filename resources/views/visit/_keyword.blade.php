{{-- Keyword mode journey: four stages that open in turn. visit.js fills [data-kw] text, the campaign images and the video from /links/get-link-info. --}}
<section id="keyword-guide" class="journey" data-mode-only="keyword" aria-labelledby="journey-title" hidden>
  <header class="journey-head">
    <p class="journey-kicker">How to get the code</p>
    <h2 id="journey-title" class="journey-title">Four quick stages</h2>
  </header>

  <ol class="stages">
    <li class="stage" data-stage="1">
      <h3 class="stage-heading">
        <button type="button" class="stage-toggle" id="stage-1-toggle" aria-expanded="false" aria-controls="stage-1-panel">
          <span class="stage-badge" aria-hidden="true"><span class="stage-num">1</span>@include('visit._icon', ['name' => 'check', 'class' => 'stage-tick'])</span>
          <span class="stage-name">Search<span class="stage-hint">Look up the keyword on Google</span></span>
          <span class="stage-chip">@include('visit._icon', ['name' => 'check']) Done</span>
          @include('visit._icon', ['name' => 'chevron', 'class' => 'stage-chevron'])
        </button>
      </h3>
      <div class="stage-panel" id="stage-1-panel" role="region" aria-labelledby="stage-1-toggle">
        <div class="stage-panel-inner">
          <div class="keyword-chip">
            <span class="keyword-chip-label">Keyword</span>
            <span class="keyword-chip-text" data-kw="keyword"></span>
            <button type="button" class="keyword-copy" data-copy-keyword>
              @include('visit._icon', ['name' => 'copy', 'class' => 'copy-idle'])
              @include('visit._icon', ['name' => 'check', 'class' => 'copy-done'])
              <span data-copy-label>Copy</span>
            </button>
          </div>

          <a class="action-primary" href="https://www.google.com" target="_blank" rel="noopener noreferrer" data-google-search>
            @include('visit._icon', ['name' => 'search'])
            Open Google search
            <span class="visually-hidden">(opens in a new tab)</span>
            @include('visit._icon', ['name' => 'external', 'class' => 'action-trailing'])
          </a>

          <ul class="tips">
            <li class="tip">@include('visit._icon', ['name' => 'browser']) <span>Use <strong>Chrome</strong> for the smoothest run</span></li>
            <li class="tip">@include('visit._icon', ['name' => 'ban']) <span>Skip results marked <strong>Sponsored</strong></span></li>
            <li class="tip">@include('visit._icon', ['name' => 'eye-off']) <span>Stay out of <strong>incognito</strong> mode</span></li>
          </ul>

          <button type="button" class="stage-next" data-stage-next>I have searched @include('visit._icon', ['name' => 'arrow-right'])</button>
        </div>
      </div>
    </li>

    <li class="stage" data-stage="2">
      <h3 class="stage-heading">
        <button type="button" class="stage-toggle" id="stage-2-toggle" aria-expanded="false" aria-controls="stage-2-panel">
          <span class="stage-badge" aria-hidden="true"><span class="stage-num">2</span>@include('visit._icon', ['name' => 'check', 'class' => 'stage-tick'])</span>
          <span class="stage-name">Visit the site<span class="stage-hint">Open it from page 1 of the results</span></span>
          <span class="stage-chip">@include('visit._icon', ['name' => 'check']) Done</span>
          @include('visit._icon', ['name' => 'chevron', 'class' => 'stage-chevron'])
        </button>
      </h3>
      <div class="stage-panel" id="stage-2-panel" role="region" aria-labelledby="stage-2-toggle">
        <div class="stage-panel-inner">
          <p class="stage-text">Find this site on page 1 of the results and open it:</p>
          <p class="site-chip">@include('visit._icon', ['name' => 'globe']) <span data-kw="domain"></span></p>
          <p class="stage-note">The stars hide part of the address. Pick the result whose address matches the visible letters.</p>

          <details class="example">
            <summary>@include('visit._icon', ['name' => 'image']) Show an example @include('visit._icon', ['name' => 'chevron', 'class' => 'example-chevron'])</summary>
            <figure class="shot" data-shot="google">
              <img alt="The partner website in the search results" hidden>
              <div class="shot-default mock-results" aria-hidden="true">
                <div class="mock-searchbar">@include('visit._icon', ['name' => 'search']) <span data-kw="keyword"></span></div>
                <div class="mock-result is-dim"><span class="mock-line"></span><span class="mock-line is-short"></span></div>
                <div class="mock-result is-target">
                  <span class="mock-site"><span class="mock-favicon"></span><span data-kw="domain"></span></span>
                  <span class="mock-heading"><span data-kw="keyword"></span>: a complete guide</span>
                  <span class="mock-line"></span>
                </div>
                <div class="mock-result is-dim"><span class="mock-line"></span><span class="mock-line is-short"></span></div>
              </div>
              <figcaption>Open the result with the matching address.</figcaption>
            </figure>
          </details>

          <button type="button" class="stage-next" data-stage-next>I found the site @include('visit._icon', ['name' => 'arrow-right'])</button>
        </div>
      </div>
    </li>

    <li class="stage" data-stage="3">
      <h3 class="stage-heading">
        <button type="button" class="stage-toggle" id="stage-3-toggle" aria-expanded="false" aria-controls="stage-3-panel">
          <span class="stage-badge" aria-hidden="true"><span class="stage-num">3</span>@include('visit._icon', ['name' => 'check', 'class' => 'stage-tick'])</span>
          <span class="stage-name">Get the code<span class="stage-hint">Press GET CODE at the end of the article</span></span>
          <span class="stage-chip">@include('visit._icon', ['name' => 'check']) Done</span>
          @include('visit._icon', ['name' => 'chevron', 'class' => 'stage-chevron'])
        </button>
      </h3>
      <div class="stage-panel" id="stage-3-panel" role="region" aria-labelledby="stage-3-toggle">
        <div class="stage-panel-inner">
          <ul class="tips tips-flow">
            <li class="tip">@include('visit._icon', ['name' => 'arrow-down']) <span>Scroll to the end of the article and press <span class="get-code-chip">GET CODE</span>, then wait for its timer.</span></li>
            <li class="tip">@include('visit._icon', ['name' => 'pointer']) <span>When it asks, click any link on that site, then scroll back to the same spot.</span></li>
            <li class="tip">@include('visit._icon', ['name' => 'hourglass']) <span>After a few more seconds your code shows up there. Copy it.</span></li>
          </ul>

          <details class="example">
            <summary>@include('visit._icon', ['name' => 'image']) Show an example @include('visit._icon', ['name' => 'chevron', 'class' => 'example-chevron'])</summary>
            <div class="example-pair">
              <figure class="shot" data-shot="button">
                <img alt="The GET CODE button at the end of the article" hidden>
                <div class="shot-default mock-article" aria-hidden="true">
                  <span class="mock-line"></span>
                  <span class="mock-line"></span>
                  <span class="mock-line is-short"></span>
                  <span class="mock-cta">GET CODE</span>
                </div>
                <figcaption>The GET CODE button sits at the end of the article.</figcaption>
              </figure>
              <figure class="shot" data-shot="position">
                <img alt="Click any link, then scroll back to the code box" hidden>
                <div class="shot-default mock-nav" aria-hidden="true">
                  <span class="mock-nav-row">
                    <span class="mock-nav-item is-target">Home</span>
                    <span class="mock-nav-item">Guides</span>
                    <span class="mock-nav-item">Tips</span>
                  </span>
                  <span class="mock-line"></span>
                  <span class="mock-line is-short"></span>
                  <span class="mock-code">Promo code: <b>k7m4qx</b></span>
                </div>
                <figcaption>Click any link, come back, and the code appears.</figcaption>
              </figure>
            </div>
          </details>

          <button type="button" class="stage-next" data-stage-next>I have my code @include('visit._icon', ['name' => 'arrow-right'])</button>
        </div>
      </div>
    </li>

    <li class="stage" data-stage="4">
      <h3 class="stage-heading">
        <button type="button" class="stage-toggle" id="stage-4-toggle" aria-expanded="false" aria-controls="stage-4-panel">
          <span class="stage-badge" aria-hidden="true"><span class="stage-num">4</span>@include('visit._icon', ['name' => 'check', 'class' => 'stage-tick'])</span>
          <span class="stage-name">Paste it here<span class="stage-hint">Type the code and press Continue</span></span>
          <span class="stage-chip">@include('visit._icon', ['name' => 'check']) Done</span>
          @include('visit._icon', ['name' => 'chevron', 'class' => 'stage-chevron'])
        </button>
      </h3>
      <div class="stage-panel" id="stage-4-panel" role="region" aria-labelledby="stage-4-toggle">
        <div class="stage-panel-inner">
          <p class="stage-text">Put the 6-character code into the code boxes, pass the quick check and press Continue.</p>
          <div class="stage-actions">
            <button type="button" class="action-primary" data-code-paste hidden>@include('visit._icon', ['name' => 'clipboard']) Paste my code</button>
            <button type="button" class="action-ghost" data-code-jump>@include('visit._icon', ['name' => 'keyboard']) Type it in</button>
          </div>
        </div>
      </div>
    </li>
  </ol>

  <section id="keyword-video" class="video-card" aria-labelledby="keyword-video-title" hidden>
    <h3 id="keyword-video-title" class="video-title">@include('visit._icon', ['name' => 'play']) Watch how it works</h3>
    <div class="video-frame">
      <iframe
        id="keyword-video-frame"
        title="How to get the code"
        loading="lazy"
        sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
        referrerpolicy="strict-origin-when-cross-origin"
      ></iframe>
    </div>
  </section>
</section>
