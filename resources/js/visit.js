import './images';
import './jquery';

/*
 * Visitor page: runs a short link on one page through AJAX, in one of two modes chosen by the server.
 * - wait: the server measures the dwell time of every step and signs it; this script shows the countdown on
 *   the button and the captcha on the last step.
 * - keyword: the visitor gets a code from the partner website by following four short steps, then enters
 *   it here together with the captcha.
 * The first /links/get-link-info response decides the mode; a response without "mode" means wait mode.
 */
$(function () {
  var $visit = $('#visit');

  if (!$visit.length) {
    return;
  }

  var csrf = $('meta[name="csrf-token"]').attr('content');
  var $title = $('#visit-title');
  var $button = $('#visit-button');
  var $status = $('#visit-status');
  var $code = $('#visit-code');
  var $code2 = $('#visit-code-2');
  var $button2 = $('#visit-button-2');
  var $session = $('#visit-session');
  var $sessionTime = $('#visit-session-time');
  var $captchaWrap = $('#visit-captcha-wrap');
  var $captcha = $('#visit-captcha');
  var $stop = $('#visit-stop');
  var $loading = $('#visit-loading');

  var CODE_LENGTH = 6;
  var preview = $visit.data('preview') || null;
  var siteKey = String($visit.data('captcha-site-key') || '');
  var fingerprint = buildFingerprint();
  var state = { mode: 'loading', token: null, step: 1, total: 1, captchaResponse: '', busy: false, ended: false };
  var waitTimer = null;
  var sessionTimer = null;
  var captchaWidget = null;
  var reportWidget = null;
  var reportResponse = '';

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

  /* ---------- Small helpers ---------- */

  function post(url, data) {
    return $.ajax({
      url: url,
      method: 'POST',
      data: JSON.stringify(data),
      contentType: 'application/json',
      headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
    });
  }

  function pad(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function clock(seconds) {
    return Math.floor(seconds / 60) + ':' + pad(seconds % 60);
  }

  function setStatus(text, tone) {
    $status.text(text || '').removeClass('is-error is-ok').addClass(tone ? 'is-' + tone : '');
  }

  function showMode(mode) {
    state.mode = mode;
    $visit.attr('data-mode', mode);
    $('[data-only]').each(function () {
      $(this).prop('hidden', $(this).data('only') !== mode);
    });
    $title.text($title.data('title-' + mode) || $title.text()).prop('hidden', false);
    $button.prop('hidden', false);
    $loading.prop('hidden', true);
  }

  /* A visitor who cannot go on gets one plain message and a way to start again. */
  function stopWith(text, restart) {
    state.ended = true;
    clearInterval(waitTimer);
    clearInterval(sessionTimer);
    $button.prop('hidden', true);
    $('.lk-code, .lk-guide').prop('hidden', true);
    $captchaWrap.prop('hidden', true);
    $session.prop('hidden', true);
    setStatus('');
    $('#visit-stop-text').text(text);
    $('#visit-stop-action').prop('hidden', restart === false);
    $stop.prop('hidden', false);
  }

  /* One message per error key sent by the server. */
  var ERRORS = {
    no_cookie: 'Cookies are turned off. Please turn them on in your browser, then open the link again.',
    expired: 'Your session has expired.',
    mode_changed: 'This page changed while you were here. Reloading&hellip;',
    bad_token: 'This page is out of date. Reloading&hellip;',
    no_fingerprint: 'JavaScript is needed to continue. Please enable it and reload the page.',
  };

  function handleBlocking(error) {
    if (error === 'mode_changed' || error === 'bad_token') {
      var reloadedAt = 0;

      try {
        reloadedAt = parseInt(window.sessionStorage.getItem('lk-reloaded-' + $visit.data('alias')), 10) || 0;
        window.sessionStorage.setItem('lk-reloaded-' + $visit.data('alias'), String(Date.now()));
      } catch (e) {
        reloadedAt = 0;
      }

      /* Reload once; if it happens again within a minute, stop and offer a manual restart instead of looping. */
      if (Date.now() - reloadedAt < 60000) {
        stopWith('This page is out of date. Please open the link again.');
        return true;
      }

      setStatus('This page is out of date. Reloading…');
      setTimeout(function () { window.location.reload(); }, 1200);
      return true;
    }

    if (error === 'no_cookie' || error === 'expired' || error === 'no_fingerprint') {
      stopWith($('<div>').html(ERRORS[error]).text(), error !== 'no_fingerprint');
      return true;
    }

    return false;
  }

  /* ---------- Step ads (templates are activated per step) ---------- */

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

  /* ---------- Captcha ---------- */

  function captchaApi() {
    var api = window[$visit.data('captcha-global')];

    return api && typeof api.render === 'function' ? api : null;
  }

  function withCaptcha(callback) {
    var api = captchaApi();

    if (api) {
      callback(api);
      return;
    }

    if (!document.getElementById('visit-captcha-script')) {
      var script = document.createElement('script');
      script.id = 'visit-captcha-script';
      script.src = $visit.data('captcha-script');
      script.async = true;
      script.defer = true;
      document.head.appendChild(script);
    }

    var tries = 0;
    var poll = setInterval(function () {
      tries++;

      if (captchaApi()) {
        clearInterval(poll);
        callback(captchaApi());
      } else if (tries > 100) {
        clearInterval(poll);
        setStatus('The captcha could not be loaded. Please reload the page.', 'error');
      }
    }, 150);
  }

  function renderCaptcha(element, onResponse, onExpire) {
    var width = element.parentNode.clientWidth;
    var options = {
      sitekey: siteKey,
      size: width > 0 && width < 300 ? 'compact' : 'normal',
      callback: onResponse,
      'expired-callback': onExpire,
    };

    if ($visit.data('captcha-provider') === 'turnstile') {
      options.language = 'en';
    }

    return captchaApi().render(element, options);
  }

  function showCaptcha() {
    if (!siteKey) {
      setStatus('Captcha is not configured on this site.', 'error');
      return;
    }

    $captchaWrap.prop('hidden', false);

    withCaptcha(function (api) {
      if (captchaWidget !== null) {
        return;
      }

      captchaWidget = renderCaptcha($captcha.get(0), function (response) {
        state.captchaResponse = response;
        updateButton();
      }, function () {
        state.captchaResponse = '';
        updateButton();
      });
    });
  }

  function resetCaptcha() {
    state.captchaResponse = '';

    if (captchaWidget !== null && captchaApi()) {
      captchaApi().reset(captchaWidget);
    }

    updateButton();
  }

  /* ---------- Button ---------- */

  function codeValue() {
    return String($code.val() || '').replace(/[^a-z0-9]/gi, '').toLowerCase();
  }

  function updateButton() {
    if (state.ended) {
      return;
    }

    if (state.mode === 'keyword') {
      var hasCode = codeValue().length === CODE_LENGTH;
      var ready = hasCode && state.captchaResponse !== '' && !state.busy;

      $button.prop('disabled', !ready).toggleClass('is-ready', ready).text('Click here to continue');

      if (!state.busy) {
        if (!hasCode) {
          setStatus('Enter your 6-character code.');
        } else if (state.captchaResponse === '') {
          setStatus('Complete the check above.');
        } else {
          setStatus('Ready. Press the button to continue.', 'ok');
        }
      }
    }
  }

  /* ---------- Wait mode ---------- */

  function renderWait(info) {
    if (state.mode !== 'wait') {
      showMode('wait');
    }

    state.token = info.token;
    state.step = info.step || 1;
    state.total = info.total_steps || 1;
    state.busy = false;

    var isFinal = !!info.captcha_required;
    var seconds = parseInt(info.dwell_seconds, 10) || 0;
    var label = state.step + '/' + state.total;

    showStepAds(state.step, isFinal);
    setStatus('');
    clearInterval(waitTimer);

    if (isFinal) {
      showCaptcha();
    } else {
      $captchaWrap.prop('hidden', true);
    }

    function tick() {
      if (seconds > 0) {
        $button.prop('disabled', true).text('Please wait ' + seconds + 's (' + label + ')');
        seconds--;
        return;
      }

      clearInterval(waitTimer);

      if (isFinal) {
        updateWaitFinal();
      } else {
        $button.prop('disabled', false).text('Continue (' + label + ')').off('click.visit').on('click.visit', nextStep);
      }
    }

    tick();
    waitTimer = setInterval(tick, 1000);
  }

  function updateWaitFinal() {
    var ready = state.captchaResponse !== '';

    $button.prop('disabled', !ready).toggleClass('is-ready', ready).text('Click here to continue');
    setStatus(ready ? 'Ready. Press the button.' : 'Complete the check above.', ready ? 'ok' : '');
  }

  function nextStep() {
    if (preview) {
      setStatus('This is a layout preview.');
      return;
    }

    $button.prop('disabled', true);
    post($visit.data('info-url'), { alias: $visit.data('alias'), token: state.token, fingerprint: fingerprint })
      .done(handleInfo)
      .fail(function (xhr) {
        var error = (xhr.responseJSON || {}).error;

        if (error === 'too_early') {
          setStatus('Please wait a little longer.', 'error');
          setTimeout(function () { $button.prop('disabled', false); }, 1500);
          return;
        }

        if (!handleBlocking(error)) {
          setStatus('Something went wrong. Please try again.', 'error');
          $button.prop('disabled', false);
        }
      });
  }

  /* ---------- Keyword mode ---------- */

  function youtubeOrVimeo(url) {
    var match = /^https:\/\/(?:www\.)?youtube\.com\/watch\?(?:.*&)?v=([\w-]{6,20})/.exec(url)
      || /^https:\/\/youtu\.be\/([\w-]{6,20})/.exec(url);

    if (match) {
      return 'https://www.youtube-nocookie.com/embed/' + match[1];
    }

    match = /^https:\/\/(?:www\.)?vimeo\.com\/(\d{5,12})/.exec(url);

    return match ? 'https://player.vimeo.com/video/' + match[1] : '';
  }

  function renderKeyword(info) {
    showMode('keyword');
    state.busy = false;

    var keyword = String(info.keyword || '');

    $('#visit-keyword').text(keyword);
    $('#visit-domain').text(info.display_domain || '');
    $('#visit-google').attr('href', 'https://www.google.com/search?q=' + encodeURIComponent(keyword));

    var images = info.images || {};

    $.each({ google: '#visit-example-google', button: '#visit-example-button', position: '#visit-example-position' }, function (key, selector) {
      var $holder = $(selector).empty();

      if (images[key]) {
        $holder.append($('<img>', { src: images[key], alt: 'Example picture', loading: 'lazy' })).prop('hidden', false);
      } else {
        $holder.prop('hidden', true);
      }
    });

    var embed = youtubeOrVimeo(String(info.tutorial_video_url || ''));

    if (embed) {
      $('#visit-video-frame').attr('src', embed);
    }
    $('#visit-video').prop('hidden', embed === '');

    showStepAds(1, true);
    showCaptcha();
    startSession(parseInt(info.session_seconds_left, 10) || 0);
    updateButton();
  }

  function startSession(seconds) {
    clearInterval(sessionTimer);

    function tick() {
      if (state.ended) {
        return;
      }

      $sessionTime.text(String(Math.max(0, seconds)));
      $session.toggleClass('is-low', seconds < 60);

      if (seconds <= 0) {
        clearInterval(sessionTimer);
        stopWith('Your session has expired.');
        return;
      }

      seconds--;
    }

    tick();
    sessionTimer = setInterval(tick, 1000);
  }

  var KEYWORD_ERRORS = {
    invalid_code: 'That code is not correct. Please check it and try again.',
    expired_code: 'That code has expired. Get a new one from the partner website.',
    wrong_device: 'That code was made on another device or browser. Get a new code on this one.',
    captcha_failed: 'The check did not pass. Please complete it again.',
  };

  function submitCode() {
    if (preview) {
      setStatus('This is a layout preview, so the code is not checked.');
      return;
    }

    state.busy = true;
    $button.prop('disabled', true).text('Checking…');
    setStatus('Checking your code…');

    post($visit.data('finish-url'), {
      alias: $visit.data('alias'),
      code: codeValue(),
      fingerprint: fingerprint,
      captcha_response: state.captchaResponse,
    }).done(function (data) {
      if (data && data.destination) {
        setStatus('Success. Taking you to your link…', 'ok');
        window.location.href = data.destination;
      }
    }).fail(function (xhr) {
      var body = xhr.responseJSON || {};
      var error = body.error;

      state.busy = false;

      if (error === 'locked') {
        var hourly = /hour/i.test(String(body.message || ''));

        stopWith(hourly ? 'Too many wrong codes. Please try again in about an hour.' : 'Too many wrong codes. Please open the link again to restart.', !hourly);
        return;
      }

      if (handleBlocking(error)) {
        return;
      }

      var message = KEYWORD_ERRORS[error] || 'Something went wrong. Please try again.';

      if (error === 'invalid_code' && typeof body.failures_left === 'number') {
        message += ' (' + body.failures_left + ' tries left)';
      }

      $code.addClass('is-error');
      $code2.addClass('is-error');
      resetCaptcha();
      setStatus(message, 'error');
    });
  }

  /* ---------- Info response ---------- */

  function handleInfo(info) {
    if (info.mode === 'keyword') {
      renderKeyword(info);
    } else {
      renderWait(info);
    }
  }

  /* ---------- Report an error ---------- */

  var $report = $('#visit-report');
  var $reportSend = $('#visit-report-send');
  var $reportMessage = $('#visit-report-message');

  function openReport() {
    $report.prop('hidden', false);
    $reportMessage.text('').removeClass('is-error is-ok');

    if (!siteKey) {
      return;
    }

    withCaptcha(function () {
      if (reportWidget !== null) {
        captchaApi().reset(reportWidget);
        reportResponse = '';
        $reportSend.prop('disabled', true);
        return;
      }

      reportWidget = renderCaptcha($('#visit-report-captcha').get(0), function (response) {
        reportResponse = response;
        $reportSend.prop('disabled', false);
      }, function () {
        reportResponse = '';
        $reportSend.prop('disabled', true);
      });
    });
  }

  function closeReport() {
    $report.prop('hidden', true);
  }

  function sendReport() {
    if (preview) {
      $reportMessage.text('This is a layout preview, so the report is not sent.');
      return;
    }

    $reportSend.prop('disabled', true);

    post($visit.data('report-url'), {
      alias: $visit.data('alias'),
      choice: $('input[name="report-choice"]:checked').val(),
      captcha_response: reportResponse,
    }).done(function (data) {
      $reportMessage.removeClass('is-error').addClass('is-ok').text((data && data.message) || 'Thank you. Your report has been sent.');
      setTimeout(closeReport, 1800);
    }).fail(function () {
      $reportMessage.removeClass('is-ok').addClass('is-error').text('The report could not be sent. Please complete the check and try again.');

      if (reportWidget !== null && captchaApi()) {
        captchaApi().reset(reportWidget);
      }

      reportResponse = '';
    });
  }

  /* ---------- Events ---------- */

  function onCodeInput(event) {
    var value = $(event.target).val();

    $code.val(value).removeClass('is-error');
    $code2.val(value).removeClass('is-error');
    updateButton();
  }

  $code.on('input', onCodeInput).on('keydown', function (event) {
    if (event.key === 'Enter' && !$button.prop('disabled')) {
      $button.trigger('click');
    }
  });
  $code2.on('input', onCodeInput);

  /* The second button repeats the first one for visitors who finished the steps at the bottom of the page. */
  $button2.on('click', function () {
    if ($button.prop('disabled')) {
      setStatus(state.captchaResponse === '' ? 'Complete the check at the top of the page first.' : 'Enter your 6-character code first.', 'error');
      $('html, body').animate({ scrollTop: 0 }, 250);
      return;
    }

    $button.trigger('click');
  });

  $button.on('click', function () {
    if ($button.prop('disabled')) {
      return;
    }

    if (state.mode === 'keyword') {
      submitCode();
    } else if (state.mode === 'wait' && state.captchaResponse !== '' && $captchaWrap.is(':visible')) {
      finishWait();
    }
  });

  function finishWait() {
    if (preview) {
      setStatus('This is a layout preview.');
      return;
    }

    $button.prop('disabled', true).text('Please wait…');

    post($visit.data('finish-url'), {
      alias: $visit.data('alias'),
      token: state.token,
      fingerprint: fingerprint,
      captcha_response: state.captchaResponse,
    }).done(function (data) {
      if (data && data.destination) {
        window.location.href = data.destination;
      }
    }).fail(function (xhr) {
      var error = (xhr.responseJSON || {}).error;

      if (!handleBlocking(error)) {
        setStatus('The check did not pass. Please try again.', 'error');
        resetCaptcha();
        updateWaitFinal();
      }
    });
  }

  $('#visit-copy-keyword').on('click', function () {
    var text = $('#visit-keyword').text();
    var $self = $(this);

    function done() {
      $self.text('Copied');
      setTimeout(function () { $self.text('Copy'); }, 1500);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, done);
    } else {
      var area = document.createElement('textarea');
      area.value = text;
      document.body.appendChild(area);
      area.select();
      document.execCommand('copy');
      document.body.removeChild(area);
      done();
    }
  });

  $('#visit-report-open, [data-report-open]').on('click', openReport);
  $('#visit-report-close, #visit-report-x').on('click', closeReport);
  $reportSend.on('click', sendReport);
  $report.on('click', function (event) {
    if (event.target === this) {
      closeReport();
    }
  });
  $(document).on('keydown', function (event) {
    if (event.key === 'Escape' && !$report.prop('hidden')) {
      closeReport();
    }
  });

  /* ---------- Start ---------- */

  function start() {
    if (preview) {
      if (preview.preview_state === 'expired') {
        showMode(preview.mode);
        stopWith('Your session has expired.');
        return;
      }

      handleInfo(preview);
      return;
    }

    post($visit.data('info-url'), { alias: $visit.data('alias'), fingerprint: fingerprint })
      .done(handleInfo)
      .fail(function (xhr) {
        var error = (xhr.responseJSON || {}).error;

        $loading.prop('hidden', true);

        if (!handleBlocking(error)) {
          stopWith('This page could not be loaded. Please try again.');
        }
      });
  }

  start();
});
