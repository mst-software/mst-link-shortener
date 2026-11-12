import './images';
import './jquery';

/*
 * Visitor page ("LinkCash Unlock"): runs a short link on one page through AJAX, in one of two modes chosen by the server.
 * - wait: the server measures the dwell time of every step and signs it; this script runs the dial and the
 *   countdown on the button and presents the captcha on the last step.
 * - keyword: the visitor follows four stages to get a code from the partner website, then enters it in the
 *   six code boxes together with the captcha.
 * The first /links/get-link-info response decides the mode; a response without "mode" means wait mode.
 */
$(function () {
  var $visit = $('#visit');

  if (!$visit.length) {
    return;
  }

  var $body = $('body');
  var csrf = $('meta[name="csrf-token"]').attr('content');
  var $title = $('#visit-title');
  var $step = $('#visit-step');
  var $quickCheck = $('#visit-quick-check');
  var $captcha = $('#visit-captcha');
  var $button = $('#visit-button');
  var $buttonLabel = $button.find('.unlock-button-label');
  var $status = $('#visit-status');
  var $statusText = $status.find('.status-text');
  var $message = $('#visit-message');
  var $codeGroup = $('#visit-code');
  var $cells = $('[data-code-cell]');
  var $session = $('#visit-session');
  var $sessionLabel = $('#visit-session-label');
  var $sessionSeconds = $('#visit-session-seconds');
  var $announcer = $('#visit-announcer');
  var $dial = $('#visit-dial');
  var $ring = $('#visit-ring');
  var $dialValue = $('#visit-dial-value');
  var $dialLabel = $('#visit-dial-label');
  var $dots = $('#visit-dots');
  var $stop = $('#visit-stop');
  var $stopTitle = $('#visit-stop-title');
  var $stopText = $('#visit-stop-text');
  var $stopAction = $('#visit-stop-action');
  var $stages = $('.stage');
  var dock = document.getElementById('visit-dock');

  var CODE_LENGTH = 6;
  var RING_LENGTH = 326.73;
  var CONTINUE_LABEL = 'Continue';
  var STAGE_NAMES = ['Search', 'Visit the site', 'Get the code', 'Paste it here'];
  var STEP_ERRORS = ['too_early', 'bad_token', 'expired', 'no_cookie', 'no_fingerprint', 'mode_changed'];
  var CAPTCHA_FAILED = 'The quick check did not go through. Please try it again.';
  var RESTART = { label: 'Start again', href: $visit.data('start-url'), icon: 'refresh' };
  var STOPS = {
    expired: { icon: 'clock', title: 'Session expired', text: 'The time for this session has run out. Start again to get a fresh one.', action: RESTART },
    bad_token: { icon: 'alert', title: 'This page is out of date', text: 'Something changed since the page opened. Start again to continue.', action: RESTART },
    no_cookie: { icon: 'alert', title: 'Cookies are turned off', text: 'This page needs cookies to remember your progress. Allow cookies for this site, then start again.', action: RESTART },
    no_fingerprint: { icon: 'alert', title: 'JavaScript is needed', text: 'Please enable JavaScript in your browser, then reload this page.', action: { label: 'Reload page', href: window.location.href } },
    not_found: { icon: 'alert', title: 'Link not found', text: 'This link does not exist or has been removed.', action: { label: 'Go to LinkCash', href: $visit.data('home-url') } },
  };

  var state = {
    mode: null,
    step: null,
    total: null,
    token: null,
    captchaRequired: false,
    captchaResponse: '',
    awaitingInput: false,
    stopped: false,
    busy: false,
    ready: false,
    stage: 1,
    stagesDone: [],
    sessionTotal: 0,
    announced: {},
  };
  var waitTimer = null;
  var sessionTimer = null;
  var preview = $visit.data('preview') || null;
  var siteKey = String($visit.data('captcha-site-key') || '');
  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fingerprint = buildFingerprint();

  /* ---------- Fingerprint ---------- */

  function hash(text, seed) {
    var h1 = 0xdeadbeef ^ seed;
    var h2 = 0x41c6ce57 ^ seed;

    for (var i = 0; i < text.length; i++) {
      var ch = text.charCodeAt(i);
      h1 = Math.imul(h1 ^ ch, 2654435761);
      h2 = Math.imul(h2 ^ ch, 1597334677);
    }

    h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909);

    return ('00000000' + (h1 >>> 0).toString(16)).slice(-8) + ('00000000' + (h2 >>> 0).toString(16)).slice(-8);
  }

  function canvasData() {
    try {
      var canvas = document.createElement('canvas');
      var ctx = canvas.getContext('2d');

      if (!ctx) {
        return '';
      }

      canvas.width = 240;
      canvas.height = 60;
      ctx.textBaseline = 'top';
      ctx.font = '16px Arial';
      ctx.fillStyle = '#f60';
      ctx.fillRect(100, 1, 62, 20);
      ctx.fillStyle = '#069';
      ctx.fillText('LinkCash, fingerprint <canvas> 1.0', 2, 15);
      ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
      ctx.fillText('LinkCash, fingerprint <canvas> 1.0', 4, 17);

      return canvas.toDataURL();
    } catch (e) {
      return '';
    }
  }

  function buildFingerprint() {
    var screenInfo = window.screen || {};
    var width = screenInfo.width || 0;
    var height = screenInfo.height || 0;
    var timeZone = '';

    try {
      timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (e) {
      timeZone = '';
    }

    /* Sorted dimensions keep the value stable when a phone is rotated. */
    var parts = [
      Math.min(width, height) + 'x' + Math.max(width, height),
      screenInfo.colorDepth || 0,
      timeZone,
      new Date().getTimezoneOffset(),
      navigator.language || '',
      (navigator.languages || []).join(','),
      navigator.hardwareConcurrency || 0,
      hash(canvasData(), 7),
    ].join('|');

    return hash(parts, 1) + hash(parts, 2);
  }

  /* ---------- UI helpers ---------- */

  /* A notice box: tone "error", "success" or "info"; an empty text hides it. */
  function setNotice($notice, text, tone) {
    $notice
      .toggleClass('is-error', tone === 'error')
      .toggleClass('is-success', tone === 'success')
      .prop('hidden', !text)
      .find('.notice-text').text(text || '');
  }

  function showMessage(text, tone) {
    setNotice($message, text, tone || 'info');
    updateDockSpace();
  }

  function announce(text) {
    $announcer.text('');
    setTimeout(function () { $announcer.text(text); }, 50);
  }

  /* The button is grey while it cannot be used and turns lime once it can. */
  function setButton(label, enabled, busy) {
    var becomesReady = !!enabled && !state.ready;

    state.ready = !!enabled;
    $buttonLabel.text(label);
    $button.prop('disabled', !enabled).toggleClass('is-ready', !!enabled).toggleClass('is-busy', !!busy);

    if (becomesReady && !reducedMotion) {
      $button.removeClass('just-ready');
      void $button.get(0).offsetWidth;
      $button.addClass('just-ready');
    }
  }

  /* The status line under the button: "pending", "busy", "ready" (with a check icon) or "off" (hidden). */
  function setStatus(text, tone) {
    $statusText.text(text || '');
    $status.attr('data-tone', text ? tone : 'off');
  }

  function showMode(mode) {
    state.mode = mode;
    $visit.attr('data-mode', mode);
    $body.attr('data-mode', mode);
    $title.text($title.data('title-' + mode));

    $('[data-mode-only]').each(function () {
      $(this).prop('hidden', $(this).data('mode-only') !== mode);
    });

    updateDockSpace();
  }

  function post(url, data) {
    return $.ajax({
      url: url,
      method: 'POST',
      data: data,
      dataType: 'json',
      headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
    });
  }

  function scrollIntoView(element) {
    element.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'center' });
  }

  /* On phones the dock is fixed to the screen bottom; reserve its height so it never covers the footer. */
  function updateDockSpace() {
    var fixed = dock && window.getComputedStyle(dock).position === 'fixed' && !state.stopped;

    document.documentElement.style.setProperty('--dock-space', fixed ? dock.offsetHeight + 'px' : '0px');
  }

  /* ---------- Dial ---------- */

  function setDial(fraction, value, label, tone) {
    var clamped = Math.max(0, Math.min(1, fraction));

    $ring.css('stroke-dashoffset', (RING_LENGTH * (1 - clamped)).toFixed(2));
    $dialValue.text(value);
    $dialLabel.text(label);
    $dial.attr('data-state', tone || 'running');
  }

  function renderDots(total, current, done) {
    if ($dots.children().length !== total) {
      $dots.empty();

      for (var i = 0; i < total; i++) {
        $dots.append('<li></li>');
      }
    }

    $dots.children().each(function (index) {
      var number = index + 1;

      $(this)
        .toggleClass('is-done', done.indexOf(number) !== -1)
        .toggleClass('is-current', number === current && done.indexOf(number) === -1);
    });
  }

  function clock(seconds) {
    var minutes = Math.floor(seconds / 60);
    var rest = seconds % 60;

    return minutes + ':' + (rest < 10 ? '0' : '') + rest;
  }

  /* ---------- Stopping ---------- */

  /* Replace the controls with a clear "what now" panel. */
  function stopWith(stop) {
    state.awaitingInput = false;
    state.stopped = true;
    state.busy = false;
    clearInterval(waitTimer);
    clearInterval(sessionTimer);
    $cells.prop('disabled', true);
    setButton('Unavailable', false);
    setStatus('', 'off');
    showMessage('');

    $stop.attr('data-icon', stop.icon || 'alert');
    $stopTitle.text(stop.title);
    $stopText.text(stop.text);

    if (stop.action) {
      $stopAction.attr('href', stop.action.href).prop('hidden', false).find('.unlock-button-label').text(stop.action.label);
    } else {
      $stopAction.prop('hidden', true);
    }

    $visit.addClass('is-stopped');
    $stop.prop('hidden', false);
    $stopTitle.trigger('focus');
    updateDockSpace();
  }

  /* The server says "hour" when the hourly limit per network locked the code entry, not just this session. */
  function lockStop(body) {
    var hourly = /hour/i.test(String(body.message || ''));

    return {
      icon: 'lock',
      title: 'Code entry locked',
      text: hourly
        ? 'Too many wrong codes. Please try again in about an hour.'
        : 'Too many wrong codes. Please open the link again to restart.',
      action: hourly ? null : RESTART,
    };
  }

  /* Put the button back after a failed request: re-check the inputs, or offer the step again. */
  function restoreButton() {
    if (state.awaitingInput) {
      updateReadiness();
      return;
    }

    setButton(CONTINUE_LABEL, true);
    setStatus('Ready', 'ready');
  }

  function handleError(xhr) {
    var body = xhr.responseJSON || {};
    state.busy = false;

    if (xhr.status === 422 && body.error === 'too_early') {
      showMessage(body.message || 'Please wait until the timer has finished.', 'error');
      setTimeout(restoreButton, 1500);
      return;
    }

    if (xhr.status === 422 && body.error === 'mode_changed') {
      stopWith({ icon: 'alert', title: 'This page was updated', text: 'Reloading to pick up the latest version…', action: null });
      setTimeout(function () { window.location.reload(); }, 1500);
      return;
    }

    if (xhr.status === 422 && body.error) {
      stopWith(STOPS[body.error] || { icon: 'alert', title: 'Something went wrong', text: body.message || 'Please start again.', action: RESTART });
      return;
    }

    if (xhr.status === 404) {
      stopWith(STOPS.not_found);
      return;
    }

    showMessage('Something went wrong. Please try again.', 'error');
    restoreButton();
  }

  /* ---------- Loading the link ---------- */

  function requestStep() {
    if (preview) {
      previewNextStep();
      return;
    }

    state.busy = true;
    setButton('Please wait…', false, true);
    setStatus('Loading…', 'busy');
    showMessage('');

    var data = { alias: $visit.data('alias'), fingerprint: fingerprint };

    if (state.token) {
      data.token = state.token;
    }

    post($visit.data('info-url'), data).done(renderInfo).fail(handleError);
  }

  function renderInfo(info) {
    if (info.mode === 'keyword') {
      renderKeyword(info);
    } else {
      renderWaitStep(info);
    }
  }

  /* ---------- Step ads ---------- */

  function activateAd($container) {
    var template = $container.children('template').get(0);

    if (!template || $container.data('loaded')) {
      return;
    }

    var fragment = template.content.cloneNode(true);

    /* Scripts cloned from a template do not run, so rebuild them. */
    $(fragment).find('script').each(function () {
      var copy = document.createElement('script');

      $.each(this.attributes, function (i, attr) {
        copy.setAttribute(attr.name, attr.value);
      });
      copy.text = this.text;
      this.parentNode.replaceChild(copy, this);
    });

    $container.append(fragment).data('loaded', true);
  }

  function showStepAds(step, isFinal) {
    var wanted = ['step' + step];

    if (isFinal) {
      wanted.push('final');
    }

    $('[data-ad-step-bound]').each(function () {
      var $container = $(this);
      var show = wanted.indexOf($container.data('ad-placement')) !== -1;

      $container.prop('hidden', !show);

      if (show) {
        activateAd($container);
      }
    });
  }

  /* ---------- Wait mode ---------- */

  function renderWaitStep(info) {
    if (state.mode !== 'wait') {
      showMode('wait');
    }

    state.busy = false;
    state.step = info.step;
    state.total = info.total_steps;
    state.token = info.token;
    state.captchaRequired = !!info.captcha_required;
    state.captchaResponse = '';
    state.awaitingInput = false;

    var done = [];

    for (var i = 1; i < info.step; i++) {
      done.push(i);
    }

    $step.text('Step ' + info.step + ' of ' + info.total_steps);
    renderDots(info.total_steps, info.step, done);
    showStepAds(info.step, state.captchaRequired);
    $quickCheck.prop('hidden', true);
    runCountdown(Math.max(0, parseInt(info.dwell_seconds, 10) || 0));
  }

  function runCountdown(seconds) {
    clearInterval(waitTimer);

    /* A small margin so the request never reaches the server before its own clock allows it. */
    var startedAt = Date.now();
    var endsAt = startedAt + seconds * 1000 + 300;

    function tick() {
      var now = Date.now();
      var left = Math.max(0, Math.ceil((endsAt - now) / 1000));

      if (now >= endsAt) {
        clearInterval(waitTimer);
        countdownFinished();
        return;
      }

      setDial((now - startedAt) / (endsAt - startedAt), String(left), left === 1 ? 'second' : 'seconds');
      setButton('Please wait ' + left + 's (' + state.step + '/' + state.total + ')', false);
    }

    setStatus('Please wait for the timer to finish', 'pending');
    tick();
    waitTimer = setInterval(tick, 250);
  }

  function countdownFinished() {
    setDial(1, '', state.captchaRequired ? 'Last step' : 'Ready', 'complete');

    if (!state.captchaRequired) {
      setButton(CONTINUE_LABEL, true);
      setStatus('Ready', 'ready');
      return;
    }

    state.awaitingInput = true;
    showCaptcha();
    updateReadiness();
  }

  /* ---------- Keyword mode: guide ---------- */

  function imageUrl(path) {
    if (!path) {
      return null;
    }

    path = String(path);

    return /^(https?:)?\/\//.test(path) || path.charAt(0) === '/' ? path : '/storage/' + path;
  }

  /* YouTube (watch, youtu.be, embed, shorts, live) and Vimeo links; anything else hides the video block. */
  function videoEmbedUrl(url) {
    var text = String(url || '').trim();
    var youtube = text.match(
      /(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/))([A-Za-z0-9_-]{11})/
    );

    if (youtube) {
      return 'https://www.youtube-nocookie.com/embed/' + youtube[1];
    }

    var vimeo = text.match(/^(?:https?:)?\/\/(?:www\.|player\.)?vimeo\.com\/(?:video\/)?(\d+)(?:[/?#]|$)/);

    return vimeo ? 'https://player.vimeo.com/video/' + vimeo[1] : null;
  }

  function fillGuide(info) {
    var images = info.images || {};
    var keyword = String(info.keyword || '');

    $('[data-kw="keyword"]').text(keyword);
    $('[data-kw="domain"]').text(info.display_domain || '');
    $('[data-google-search]').attr('href', 'https://www.google.com/search?q=' + encodeURIComponent(keyword));

    /* Campaign images replace the built-in illustrations when the admin uploaded them. */
    $('[data-shot]').each(function () {
      var url = imageUrl(images[$(this).data('shot')]);
      var $default = $(this).children('.shot-default');
      var $img = $(this).children('img');

      if (!url) {
        return;
      }

      $img.attr('src', url).prop('hidden', false).one('error', function () {
        $img.prop('hidden', true);
        $default.prop('hidden', false);
      });
      $default.prop('hidden', true);
    });

    var embed = videoEmbedUrl(info.tutorial_video_url);

    if (embed) {
      $('#keyword-video-frame').attr('src', embed);
    }

    $('#keyword-video').prop('hidden', !embed);
  }

  /* ---------- Keyword mode: stages ---------- */

  function stageElement(number) {
    return $stages.filter('[data-stage="' + number + '"]');
  }

  function setStageOpen(number, open) {
    var $stage = stageElement(number);

    $stage.toggleClass('is-open', open);
    $stage.find('.stage-toggle').attr('aria-expanded', open ? 'true' : 'false');
  }

  function renderStages() {
    $stages.each(function () {
      var number = $(this).data('stage');
      var done = state.stagesDone.indexOf(number) !== -1;

      $(this).toggleClass('is-done', done).toggleClass('is-current', number === state.stage && !done);
    });

    renderDots(STAGE_NAMES.length, state.stage, state.stagesDone);

    $step.text(state.stagesDone.length >= STAGE_NAMES.length
      ? 'All stages done'
      : 'Stage ' + state.stage + ' of ' + STAGE_NAMES.length + ': ' + STAGE_NAMES[state.stage - 1]);
  }

  /* Mark a stage done: it folds away and the next one opens. */
  function completeStage(number) {
    if (state.stagesDone.indexOf(number) === -1) {
      state.stagesDone.push(number);
    }

    setStageOpen(number, false);

    if (number >= state.stage && number < STAGE_NAMES.length) {
      state.stage = number + 1;
      setStageOpen(state.stage, true);
    }

    renderStages();
  }

  function completeAllStages() {
    for (var i = 1; i <= STAGE_NAMES.length; i++) {
      if (state.stagesDone.indexOf(i) === -1) {
        state.stagesDone.push(i);
      }

      setStageOpen(i, false);
    }

    state.stage = STAGE_NAMES.length;
    renderStages();
  }

  /* Re-open a stage, for example stage 3 when the code has expired. */
  function reopenStage(number) {
    state.stagesDone = $.grep(state.stagesDone, function (value) { return value < number; });
    state.stage = number;
    $stages.each(function () {
      setStageOpen($(this).data('stage'), $(this).data('stage') === number);
    });
    renderStages();
  }

  $('.stage-toggle').on('click', function () {
    var number = $(this).closest('.stage').data('stage');

    setStageOpen(number, $(this).attr('aria-expanded') !== 'true');
  });

  $('[data-stage-next]').on('click', function () {
    var number = $(this).closest('.stage').data('stage');

    completeStage(number);

    if (number === STAGE_NAMES.length - 1) {
      focusCode(true);
    } else {
      stageElement(number + 1).find('.stage-toggle').trigger('focus');
    }
  });

  $('[data-google-search]').on('click', function () {
    completeStage(1);
  });

  $('[data-copy-keyword]').on('click', function () {
    var $copy = $(this);
    var text = $('.keyword-chip-text').first().text();

    if (!text) {
      return;
    }

    function copied() {
      $copy.addClass('is-copied').find('[data-copy-label]').text('Copied');
      announce('Keyword copied');
      setTimeout(function () {
        $copy.removeClass('is-copied').find('[data-copy-label]').text('Copy');
      }, 1800);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(copied, function () {
        if (legacyCopy(text)) {
          copied();
        }
      });
    } else if (legacyCopy(text)) {
      copied();
    }
  });

  function legacyCopy(text) {
    var area = document.createElement('textarea');
    var copied = false;

    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();

    try {
      copied = document.execCommand('copy');
    } catch (e) {
      copied = false;
    }

    document.body.removeChild(area);

    return copied;
  }

  /* ---------- Keyword mode: session ---------- */

  function renderKeyword(info) {
    showMode('keyword');
    fillGuide(info);

    state.busy = false;
    state.token = info.token || null;
    state.captchaRequired = true;
    state.captchaResponse = '';
    state.awaitingInput = true;

    state.stage = 1;
    state.stagesDone = [];
    setStageOpen(1, true);
    renderStages();

    if (navigator.clipboard && navigator.clipboard.readText) {
      $('[data-code-paste]').prop('hidden', false);
    }

    /* Step ads belong to wait mode; keep them hidden next to the code box. */
    showStepAds(0, false);
    runSessionTimer(Math.max(0, parseInt(info.session_seconds_left, 10) || 0));

    if (!state.stopped) {
      showCaptcha();
      updateReadiness();
    }
  }

  function runSessionTimer(seconds) {
    clearInterval(sessionTimer);

    var endsAt = Date.now() + seconds * 1000;
    state.sessionTotal = Math.max(seconds, 1);

    function tick() {
      var left = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
      var low = left < 60;

      setDial(left / state.sessionTotal, clock(left), low ? 'Hurry up' : 'Time left', low ? 'low' : 'running');
      $sessionSeconds.text(clock(left));
      $sessionLabel.text(low ? 'Under a minute left' : 'Session time left:');
      $sessionSeconds.prop('hidden', low);
      $session.toggleClass('is-low', low);
      announceTime(left);

      if (left <= 0) {
        clearInterval(sessionTimer);
        setDial(0, '0:00', 'Time is up', 'over');
        stopWith(STOPS.expired);
      }
    }

    tick();
    sessionTimer = setInterval(tick, 250);
  }

  /* Screen readers hear the remaining time at a few milestones, not every second. */
  function announceTime(left) {
    var milestones = [[300, '5 minutes left in this session.'], [60, 'One minute left in this session.'], [30, '30 seconds left in this session.']];

    $.each(milestones, function (i, milestone) {
      if (left <= milestone[0] && left > 0 && !state.announced[milestone[0]]) {
        state.announced[milestone[0]] = true;

        if (left > milestone[0] - 5) {
          announce(milestone[1]);
        }
      }
    });
  }

  /* ---------- Keyword mode: code boxes ---------- */

  function sanitizeCode(text) {
    return String(text || '').toLowerCase().replace(/[^a-z0-9]/g, '');
  }

  function enteredCode() {
    return $cells.map(function () { return this.value; }).get().join('');
  }

  /* Spread characters over the boxes from the given box; returns the box to focus next. */
  function fillCode(from, text) {
    var chars = sanitizeCode(text).split('');

    for (var i = 0; i < chars.length && from + i < CODE_LENGTH; i++) {
      $cells.eq(from + i).val(chars[i]);
    }

    return Math.min(from + chars.length, CODE_LENGTH - 1);
  }

  function focusCell(index) {
    var cell = $cells.get(index);

    if (cell) {
      cell.focus();
    }
  }

  function firstEmptyCell() {
    for (var i = 0; i < CODE_LENGTH; i++) {
      if (!$cells.eq(i).val()) {
        return i;
      }
    }

    return CODE_LENGTH - 1;
  }

  function focusCode(scroll) {
    var cell = $cells.get(firstEmptyCell());

    if (!cell || cell.disabled) {
      return;
    }

    if (scroll && window.getComputedStyle(dock).position !== 'fixed') {
      scrollIntoView($codeGroup.get(0));
      cell.focus({ preventScroll: true });
      return;
    }

    cell.focus();
  }

  function codeChanged() {
    var code = enteredCode();

    $cells.each(function () {
      $(this).toggleClass('has-value', this.value !== '').data('previous', this.value);
    });

    $codeGroup.removeClass('is-error is-shaking').toggleClass('is-complete', code.length === CODE_LENGTH);

    if (!$message.prop('hidden') && $message.hasClass('is-error')) {
      showMessage('');
    }

    if (code.length === CODE_LENGTH) {
      completeAllStages();
    }

    updateReadiness();
  }

  $cells.on('focus', function () {
    var cell = this;

    setTimeout(function () { cell.select(); }, 0);
  });

  $cells.on('input', function () {
    var index = $cells.index(this);
    var previous = $(this).data('previous') || '';
    var value = sanitizeCode(this.value);

    /* Typing over a filled box gives two characters: keep the new one. */
    if (value.length === 2 && previous && value.indexOf(previous) !== -1) {
      value = value.replace(previous, '') || previous;
    }

    if (value.length > 1) {
      this.value = '';
      focusCell(fillCode(index, value));
    } else {
      this.value = value;

      if (value && index < CODE_LENGTH - 1) {
        focusCell(index + 1);
      }
    }

    codeChanged();
  });

  $cells.on('keydown', function (e) {
    var index = $cells.index(this);

    if (e.key === 'Backspace' && this.value === '' && index > 0) {
      e.preventDefault();
      $cells.eq(index - 1).val('');
      focusCell(index - 1);
      codeChanged();
    } else if (e.key === 'ArrowLeft' && index > 0) {
      e.preventDefault();
      focusCell(index - 1);
    } else if (e.key === 'ArrowRight' && index < CODE_LENGTH - 1) {
      e.preventDefault();
      focusCell(index + 1);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      $button.trigger('click');
    }
  });

  $cells.on('paste', function (e) {
    var clipboard = e.originalEvent && e.originalEvent.clipboardData;
    var text = sanitizeCode(clipboard ? clipboard.getData('text') : '');

    if (!text) {
      return;
    }

    e.preventDefault();
    focusCell(fillCode(text.length >= CODE_LENGTH ? 0 : $cells.index(this), text));
    codeChanged();
  });

  $('[data-code-paste]').on('click', function () {
    navigator.clipboard.readText().then(function (text) {
      var code = sanitizeCode(text);

      if (!code) {
        showMessage('Your clipboard has no code yet. Copy the code from the partner site first.', 'info');
        focusCode(true);
        return;
      }

      $cells.val('');
      focusCell(fillCode(0, code));
      codeChanged();
    }, function () {
      showMessage('Pasting was blocked. Press and hold the first box, then choose Paste.', 'info');
      focusCode(true);
    });
  });

  $('[data-code-jump]').on('click', function () {
    focusCode(true);
  });

  function captchaPassed() {
    return state.captchaResponse !== '' || !siteKey;
  }

  /* The last step is ready when the code (keyword mode) and the captcha are both in place. */
  function updateReadiness() {
    if (!state.awaitingInput || state.busy || state.stopped) {
      return;
    }

    if (state.mode === 'keyword' && enteredCode().length < CODE_LENGTH) {
      setButton(CONTINUE_LABEL, false);
      setStatus('Enter the code', 'pending');
      return;
    }

    if (!captchaPassed()) {
      setButton(CONTINUE_LABEL, false);
      setStatus('Verify you are human', 'pending');
      return;
    }

    setButton(CONTINUE_LABEL, true);
    setStatus('Ready', 'ready');
  }

  function codeErrorText(body) {
    var left = parseInt(body.failures_left, 10);
    var attempts = isNaN(left) ? '' : ' ' + left + (left === 1 ? ' attempt' : ' attempts') + ' left.';

    switch (body.error) {
      case 'captcha_failed':
        return CAPTCHA_FAILED + (state.mode === 'keyword' ? ' Your code has not been used.' : '');
      case 'invalid_code':
        return 'That code does not match. Check each character and try again.' + attempts;
      case 'expired_code':
        return 'This code has expired. Get a new code from the partner site and enter it here.';
      case 'wrong_device':
        return 'This code was made in a different browser. Please get the code in this browser.' + attempts;
      default:
        return null;
    }
  }

  /* Show a refused code: message, red boxes with a short shake, and focus back on the boxes. */
  function showCodeError(body, text) {
    showMessage(text, 'error');

    if (state.mode !== 'keyword' || body.error === 'captcha_failed') {
      return;
    }

    if (body.error === 'expired_code') {
      $cells.val('');
      codeChanged();
      reopenStage(3);
      showMessage(text, 'error');
    }

    $codeGroup.addClass('is-error');

    if (!reducedMotion) {
      $codeGroup.removeClass('is-shaking');
      void $codeGroup.get(0).offsetWidth;
      $codeGroup.addClass('is-shaking');
    }

    focusCell(0);
  }

  /* ---------- Captcha (Turnstile, hCaptcha or reCAPTCHA; all share the same render API) ---------- */

  var captchaQueue = [];
  var captchaLoading = false;
  var captchaWidget = null;

  function captchaApi() {
    return window[$visit.data('captcha-global')];
  }

  /* Run the callback once the captcha script is loaded; the script is added to the page only once. */
  function withCaptcha(callback) {
    var api = captchaApi();

    if (api && typeof api.render === 'function') {
      callback(api);
      return;
    }

    captchaQueue.push(callback);

    if (captchaLoading) {
      return;
    }

    captchaLoading = true;
    window.lcCaptchaReady = function () {
      var queued = captchaQueue;
      captchaQueue = [];

      $.each(queued, function (i, fn) {
        fn(captchaApi());
      });
    };

    var script = document.createElement('script');
    script.src = $visit.data('captcha-script') + '&onload=lcCaptchaReady';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
  }

  /* Widget options: the standard widget is 300px wide, so narrow boxes get the compact one; Turnstile is kept in English like the page. */
  function captchaOptions(element, callbacks) {
    var options = $.extend({
      sitekey: siteKey,
      size: element.clientWidth > 0 && element.clientWidth < 300 ? 'compact' : 'normal',
    }, callbacks);

    if ($visit.data('captcha-provider') === 'turnstile') {
      options.language = 'en';
    }

    return options;
  }

  function showCaptcha() {
    if (!siteKey) {
      $quickCheck.prop('hidden', true);
      showMessage('Captcha is not configured on this site.', 'error');
      return;
    }

    $quickCheck.prop('hidden', false);

    withCaptcha(function (api) {
      if (captchaWidget !== null) {
        api.reset(captchaWidget);
        return;
      }

      captchaWidget = api.render($captcha.get(0), captchaOptions($captcha.get(0), {
        callback: function (response) {
          state.captchaResponse = response;
          updateReadiness();
        },
        'expired-callback': function () {
          state.captchaResponse = '';
          updateReadiness();
        },
      }));
    });
  }

  function resetCaptcha() {
    state.captchaResponse = '';

    if (captchaWidget !== null && captchaApi()) {
      captchaApi().reset(captchaWidget);
    }
  }

  /* ---------- Finishing ---------- */

  function finish() {
    if (preview) {
      showMessage('This is a layout preview, so the code is not checked.', 'info');
      return;
    }

    var data = {
      alias: $visit.data('alias'),
      fingerprint: fingerprint,
      captcha_response: state.captchaResponse,
    };

    /* The step token is optional in keyword mode, where the code identifies the attempt. */
    if (state.token) {
      data.token = state.token;
    }

    if (state.mode === 'keyword') {
      data.code = enteredCode();
    }

    state.busy = true;
    setButton('Checking…', false, true);
    setStatus('Checking…', 'busy');
    showMessage('');

    post($visit.data('finish-url'), data).done(function (body) {
      state.awaitingInput = false;
      setButton('Opening your link…', false, true);
      setStatus('Ready', 'ready');
      window.location.href = body.destination;
    }).fail(function (xhr) {
      var body = xhr.responseJSON || {};

      if (xhr.status === 422 && body.error === 'locked') {
        stopWith(lockStop(body));
        return;
      }

      var codeError = xhr.status === 422 ? codeErrorText(body) : null;

      /* In keyword mode step errors go through handleError; any other refusal can be retried. */
      if (!codeError && state.mode === 'keyword' && xhr.status === 422 && body.error && STEP_ERRORS.indexOf(body.error) === -1) {
        codeError = body.message || 'Please try again.';
      }

      resetCaptcha();

      if (codeError) {
        state.busy = false;
        showCodeError(body, codeError);
        updateReadiness();
        return;
      }

      handleError(xhr);
    });
  }

  $button.on('click', function () {
    if (state.busy || $button.prop('disabled')) {
      return;
    }

    if (state.captchaRequired) {
      finish();
    } else {
      requestStep();
    }
  });

  /* ---------- Report an error ---------- */

  var dialog = document.getElementById('report-dialog');
  var $reportForm = $('#report-form');
  var $reportMessage = $('#report-message');
  var $reportCaptcha = $('#report-captcha');
  var $reportSubmit = $reportForm.find('[type="submit"]');
  var reportWidget = null;
  var reportCaptchaResponse = '';

  function updateReportSubmit() {
    $reportSubmit.prop('disabled', !!siteKey && reportCaptchaResponse === '');
  }

  function showReportCaptcha() {
    if (!siteKey) {
      $reportCaptcha.closest('.quick-check').prop('hidden', true);
      updateReportSubmit();
      return;
    }

    updateReportSubmit();

    withCaptcha(function (api) {
      if (reportWidget !== null) {
        return;
      }

      reportWidget = api.render($reportCaptcha.get(0), captchaOptions($reportCaptcha.get(0), {
        callback: function (response) {
          reportCaptchaResponse = response;
          updateReportSubmit();
        },
        'expired-callback': function () {
          reportCaptchaResponse = '';
          updateReportSubmit();
        },
      }));
    });
  }

  function resetReportCaptcha() {
    reportCaptchaResponse = '';

    if (reportWidget !== null && captchaApi()) {
      captchaApi().reset(reportWidget);
    }

    updateReportSubmit();
  }

  function openReport() {
    setNotice($reportMessage, '');

    if (dialog && typeof dialog.showModal === 'function' && !dialog.open) {
      dialog.showModal();
      showReportCaptcha();
    }
  }

  $('[data-report-open]').on('click', openReport);

  $('[data-report-close]').on('click', function () {
    dialog.close();
  });

  /* A click on the backdrop closes the dialog too. */
  $(dialog).on('click', function (e) {
    if (e.target === dialog) {
      dialog.close();
    }
  });

  $reportForm.on('submit', function (e) {
    e.preventDefault();

    if (preview) {
      setNotice($reportMessage, 'This is a layout preview, so the report is not sent.', 'info');
      return;
    }

    var choice = $reportForm.find('input[name="choice"]:checked').val();

    $reportSubmit.prop('disabled', true);

    post($visit.data('report-url'), {
      alias: $visit.data('alias'),
      choice: choice,
      reason: choice,
      detail: $('#report-detail').val(),
      captcha_response: reportCaptchaResponse,
    }).done(function (body) {
      setNotice($reportMessage, body.message || 'Thank you. Your report has been sent.', 'success');
      $('#report-detail').val('');
      setTimeout(function () { dialog.close(); }, 1800);
    }).fail(function (xhr) {
      var body = xhr.responseJSON || {};
      var text = 'Your report could not be sent. Please try again.';

      if (xhr.status === 429) {
        text = 'Too many reports. Please try again in a minute.';
      } else if (body.error === 'captcha_failed') {
        text = CAPTCHA_FAILED;
      } else if (body.errors) {
        text = body.errors[Object.keys(body.errors)[0]][0];
      }

      setNotice($reportMessage, text, 'error');
    }).always(function () {
      resetReportCaptcha();
    });
  });

  /* ---------- Local layout preview (?preview=keyword|wait&state=...) ---------- */

  function previewNextStep() {
    var next = Math.min(state.step + 1, state.total);

    renderWaitStep({
      mode: 'wait',
      step: next,
      total_steps: state.total,
      dwell_seconds: 5,
      token: 'preview',
      captcha_required: next >= state.total,
    });
  }

  function applyPreviewState(name) {
    switch (name) {
      case 'typed':
        completeStage(1);
        completeStage(2);
        focusCell(fillCode(0, 'k7m'));
        codeChanged();
        break;
      case 'ready':
        fillCode(0, 'k7m4qx');
        state.captchaResponse = state.captchaResponse || 'preview';
        codeChanged();
        break;
      case 'error':
        fillCode(0, 'k7m4qz');
        codeChanged();
        showCodeError({ error: 'invalid_code' }, codeErrorText({ error: 'invalid_code', failures_left: 3 }));
        break;
      case 'locked':
        stopWith(lockStop({ message: 'Too many wrong codes. Please open the link again.' }));
        break;
      case 'locked-hour':
        stopWith(lockStop({ message: 'Too many wrong codes from this network. Please try again in an hour.' }));
        break;
      case 'report':
        openReport();
        break;
    }
  }

  /* ---------- Start ---------- */

  $(window).on('resize orientationchange', updateDockSpace);

  if (window.ResizeObserver && dock) {
    new ResizeObserver(updateDockSpace).observe(dock);
  }

  if (preview) {
    renderInfo(preview);
    applyPreviewState(preview.preview_state);
  } else {
    requestStep();
  }
});
