import './images';
import './jquery';

/*
 * Visitor page: runs a short link on one page through AJAX, in one of two modes chosen by the server.
 * - wait: the server measures the dwell time of every step and signs it; this script shows the countdown
 *   on the button and presents the captcha on the last step.
 * - keyword: the visitor gets a code from the advertiser website and enters it with the captcha.
 * The first /links/get-link-info response decides the mode; a response without "mode" means wait mode.
 */
$(function () {
  var $visit = $('#visit');

  if (!$visit.length) {
    return;
  }

  var csrf = $('meta[name="csrf-token"]').attr('content');
  var $title = $('#visit-title');
  var $step = $('#visit-step');
  var $captcha = $('#visit-captcha');
  var $button = $('#visit-button');
  var $status = $('#visit-status');
  var $message = $('#visit-message');
  var $code = $('#visit-code');
  var $session = $('#visit-session');
  var $sessionSeconds = $('#visit-session-seconds');

  var CODE_LENGTH = 6;
  var CONTINUE_LABEL = 'Click here to continue';
  var STEP_ERRORS = ['too_early', 'bad_token', 'expired', 'no_cookie', 'no_fingerprint', 'mode_changed'];
  var STOP_MESSAGES = {
    expired: 'Your session has expired.',
    bad_token: 'This page is no longer valid.',
    no_cookie: 'Please enable cookies in your browser, then open the link again.',
  };
  var CAPTCHA_FAILED = 'The captcha check failed. Please verify you are human again.';

  var state = {
    mode: null,
    step: null,
    total: null,
    token: null,
    captchaRequired: false,
    captchaResponse: '',
    awaitingInput: false,
    sessionExpired: false,
    busy: false,
  };
  var waitTimer = null;
  var sessionTimer = null;
  var preview = $visit.data('preview') || null;
  var siteKey = String($visit.data('captcha-site-key') || '');
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

  function showMessage(text, isError, restart) {
    $message.toggleClass('visit-message-error', !!isError).text(text || '');

    if (restart) {
      $message.append(' ').append($('<a>').attr('href', $visit.data('start-url')).text('Open the link again'));
    }
  }

  /* The button is grey while it cannot be used and turns green once it can. */
  function setButton(label, enabled) {
    $button.text(label).prop('disabled', !enabled).toggleClass('is-ready', !!enabled);
  }

  /* The status line under the button: "pending" (amber dot), "ready" (green) or "off" (grey). */
  function setStatus(text, tone) {
    $status
      .text(text || '')
      .prop('hidden', !text)
      .toggleClass('is-ready', tone === 'ready')
      .toggleClass('is-off', tone === 'off');
  }

  function showMode(mode) {
    state.mode = mode;
    $visit.attr('data-mode', mode);
    $title.text($title.data('title-' + mode));

    $('[data-mode-only]').each(function () {
      $(this).prop('hidden', $(this).data('mode-only') !== mode);
    });
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

  /* Put the button back after a failed request: re-check the inputs, or offer the step again. */
  function restoreButton() {
    if (state.awaitingInput) {
      updateReadiness();
      return;
    }

    setButton(CONTINUE_LABEL, true);
    setStatus('Ready', 'ready');
  }

  function stopAll(text, restart) {
    state.awaitingInput = false;
    clearInterval(waitTimer);
    clearInterval(sessionTimer);
    $code.prop('disabled', true);
    showMessage(text, true, restart);
    setButton('Unavailable', false);
    setStatus('', 'off');
  }

  function handleError(xhr) {
    var body = xhr.responseJSON || {};
    state.busy = false;

    if (xhr.status === 422 && body.error === 'too_early') {
      showMessage(body.message, true);
      setTimeout(restoreButton, 1500);
      return;
    }

    if (xhr.status === 422 && body.error === 'mode_changed') {
      stopAll('This page has been updated. Reloading…', false);
      setTimeout(function () { window.location.reload(); }, 1500);
      return;
    }

    if (xhr.status === 422 && body.error) {
      stopAll(STOP_MESSAGES[body.error] || body.message, ['expired', 'bad_token', 'no_cookie'].indexOf(body.error) !== -1);
      return;
    }

    if (xhr.status === 404) {
      stopAll('This link does not exist or has been removed.', false);
      return;
    }

    showMessage('Something went wrong. Please try again.', true);
    restoreButton();
  }

  /* ---------- Loading the link ---------- */

  function requestStep() {
    state.busy = true;
    setButton('Please wait…', false);
    setStatus('Loading…', 'pending');
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

    $step.text('Step ' + info.step + ' of ' + info.total_steps);
    showStepAds(info.step, state.captchaRequired);
    $captcha.prop('hidden', true);
    runCountdown(Math.max(0, parseInt(info.dwell_seconds, 10) || 0));
  }

  function runCountdown(seconds) {
    clearInterval(waitTimer);

    /* A small margin so the request never reaches the server before its own clock allows it. */
    var endsAt = Date.now() + seconds * 1000 + 300;

    function tick() {
      var left = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));

      if (Date.now() >= endsAt) {
        clearInterval(waitTimer);
        countdownFinished();
        return;
      }

      setButton('Please wait ' + left + 's (' + state.step + '/' + state.total + ')', false);
    }

    setStatus('Please wait for the timer to finish', 'pending');
    tick();
    waitTimer = setInterval(tick, 250);
  }

  function countdownFinished() {
    if (!state.captchaRequired) {
      setButton(CONTINUE_LABEL, true);
      setStatus('Ready', 'ready');
      return;
    }

    state.awaitingInput = true;
    showCaptcha();
    updateReadiness();
  }

  /* ---------- Keyword mode ---------- */

  function imageUrl(path) {
    if (!path) {
      return null;
    }

    path = String(path);

    return /^(https?:)?\/\//.test(path) || path.charAt(0) === '/' ? path : '/storage/' + path;
  }

  function youtubeEmbedUrl(url) {
    var text = String(url || '');
    var match = text.match(
      /(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/))([A-Za-z0-9_-]{11})/
    );

    if (match) {
      return 'https://www.youtube-nocookie.com/embed/' + match[1];
    }

    /* Vimeo: https://vimeo.com/ID, https://vimeo.com/ID/HASH (unlisted) or https://player.vimeo.com/video/ID */
    match = text.match(/^https?:\/\/(?:www\.|player\.)?vimeo\.com\/(?:video\/)?(\d{6,12})(?:\/([0-9a-f]{6,20}))?(?:[/?#]|$)/i);

    if (match) {
      return 'https://player.vimeo.com/video/' + match[1] + (match[2] ? '?h=' + match[2] : '');
    }

    return null;
  }

  function fillGuide(info) {
    var images = info.images || {};

    $('[data-kw="keyword"]').text(info.keyword || '');
    $('[data-kw="domain"]').text(info.display_domain || '');

    /* Campaign images replace the built-in illustrations when the admin uploaded them. */
    $('[data-shot]').each(function () {
      var url = imageUrl(images[$(this).data('shot')]);
      var $default = $(this).children('.visit-shot-default');
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

    var embed = youtubeEmbedUrl(info.tutorial_video_url);

    if (embed) {
      $('#keyword-video-frame').attr('src', embed);
    }

    $('#keyword-video').prop('hidden', !embed);
  }

  function renderKeyword(info) {
    showMode('keyword');
    fillGuide(info);

    state.busy = false;
    state.token = info.token || null;
    state.captchaRequired = true;
    state.captchaResponse = '';
    state.awaitingInput = true;

    /* Step ads belong to wait mode; keep them hidden next to the code box. */
    showStepAds(0, false);
    runSessionTimer(Math.max(0, parseInt(info.session_seconds_left, 10) || 0));
    showCaptcha();
    updateReadiness();
  }

  function runSessionTimer(seconds) {
    clearInterval(sessionTimer);

    var endsAt = Date.now() + seconds * 1000;

    function tick() {
      var left = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));

      $sessionSeconds.text(left);
      $session.toggleClass('is-low', left < 60);

      if (left <= 0) {
        clearInterval(sessionTimer);
        state.sessionExpired = true;
        stopAll('Your session has expired.', true);
        setButton('Session expired', false);
      }
    }

    tick();
    sessionTimer = setInterval(tick, 250);
  }

  function enteredCode() {
    return String($code.val() || '').replace(/\s+/g, '').toLowerCase();
  }

  function captchaPassed() {
    return state.captchaResponse !== '' || !siteKey;
  }

  /* The last step is ready when the code (keyword mode) and the captcha are both in place. */
  function updateReadiness() {
    if (!state.awaitingInput || state.busy || state.sessionExpired) {
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
        return 'This code is not correct. Please check it and try again.' + attempts;
      case 'expired_code':
        return 'This code has expired. Please get a new code from the website.';
      case 'wrong_device':
        return 'This code was created on another device or browser. Please get the code on this device.' + attempts;
      default:
        return null;
    }
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

  function showCaptcha() {
    $captcha.prop('hidden', false);

    if (!siteKey) {
      $captcha.prop('hidden', true);
      showMessage('Captcha is not configured on this site.', true);
      return;
    }

    withCaptcha(function (api) {
      if (captchaWidget !== null) {
        api.reset(captchaWidget);
        return;
      }

      captchaWidget = api.render($captcha.get(0), {
        sitekey: siteKey,
        callback: function (response) {
          state.captchaResponse = response;
          updateReadiness();
        },
        'expired-callback': function () {
          state.captchaResponse = '';
          updateReadiness();
        },
      });
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
      showMessage('This is a layout preview, so the code is not checked.', false);
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
    setButton('Please wait…', false);
    setStatus('Checking…', 'pending');
    showMessage('');

    post($visit.data('finish-url'), data).done(function (body) {
      state.awaitingInput = false;
      setButton('Opening…', false);
      setStatus('Ready', 'ready');
      window.location.href = body.destination;
    }).fail(function (xhr) {
      var body = xhr.responseJSON || {};

      if (xhr.status === 422 && body.error === 'locked') {
        state.busy = false;
        stopAll(body.message || 'Too many wrong codes. Please open the link again to start over.', true);
        return;
      }

      var codeError = xhr.status === 422 ? codeErrorText(body) : null;

      /* In keyword mode step errors go through handleError; any other refusal (a failed captcha) can be retried. */
      if (!codeError && state.mode === 'keyword' && xhr.status === 422 && body.error && STEP_ERRORS.indexOf(body.error) === -1) {
        codeError = body.message || 'Please try again.';
      }

      resetCaptcha();

      if (codeError) {
        state.busy = false;
        showMessage(codeError, true);
        updateReadiness();
        $code.trigger('focus').trigger('select');
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

  $code.on('input', function () {
    showMessage('');
    updateReadiness();
  });

  $code.on('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      $button.trigger('click');
    }
  });

  $('[data-code-jump]').on('click', function () {
    var element = $code.get(0);

    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    element.focus({ preventScroll: true });
  });

  $('[data-copy-keyword]').on('click', function () {
    var $copy = $(this);
    var text = $copy.siblings('[data-kw="keyword"]').text();

    if (!navigator.clipboard || !text) {
      return;
    }

    navigator.clipboard.writeText(text).then(function () {
      $copy.text('Copied');
      setTimeout(function () { $copy.text('Copy'); }, 1500);
    });
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
      $reportCaptcha.prop('hidden', true);
      updateReportSubmit();
      return;
    }

    updateReportSubmit();

    withCaptcha(function (api) {
      if (reportWidget !== null) {
        return;
      }

      reportWidget = api.render($reportCaptcha.get(0), {
        sitekey: siteKey,
        callback: function (response) {
          reportCaptchaResponse = response;
          updateReportSubmit();
        },
        'expired-callback': function () {
          reportCaptchaResponse = '';
          updateReportSubmit();
        },
      });
    });
  }

  function resetReportCaptcha() {
    reportCaptchaResponse = '';

    if (reportWidget !== null && captchaApi()) {
      captchaApi().reset(reportWidget);
    }

    updateReportSubmit();
  }

  $('[data-report-open]').on('click', function () {
    $reportMessage.removeClass('visit-message-error').text('');

    if (dialog && typeof dialog.showModal === 'function') {
      dialog.showModal();
      showReportCaptcha();
    }
  });

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
      $reportMessage.removeClass('visit-message-error').text('This is a layout preview, so the report is not sent.');
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
      $reportMessage.removeClass('visit-message-error').text(body.message);
      $('#report-detail').val('');
      setTimeout(function () { dialog.close(); }, 1500);
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

      $reportMessage.addClass('visit-message-error').text(text);
    }).always(function () {
      resetReportCaptcha();
    });
  });

  /* ---------- Start ---------- */

  if (preview) {
    renderInfo(preview);
  } else {
    requestStep();
  }
});
