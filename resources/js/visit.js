import './images';
import './jquery';

/*
 * Visitor page: runs every step of a short link on one page through AJAX.
 * The server measures the dwell time and signs every step; this script only shows
 * the countdown, sends the browser fingerprint and presents the captcha on the last step.
 */
$(function () {
  var $visit = $('#visit');

  if (!$visit.length) {
    return;
  }

  var csrf = $('meta[name="csrf-token"]').attr('content');
  var $step = $('#visit-step');
  var $countdown = $('#visit-countdown');
  var $seconds = $('#visit-seconds');
  var $captcha = $('#visit-captcha');
  var $button = $('#visit-button');
  var $message = $('#visit-message');

  var state = { step: null, total: null, token: null, captchaRequired: false, captchaResponse: '', busy: false };
  var timer = null;
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

  function setButton(label, enabled) {
    $button.text(label).prop('disabled', !enabled);
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

  function handleError(xhr) {
    var body = xhr.responseJSON || {};
    state.busy = false;

    if (xhr.status === 422 && body.error === 'too_early') {
      showMessage(body.message, true);
      setTimeout(function () { setButton(state.captchaRequired ? 'Get Link' : 'Continue', true); }, 1500);
      return;
    }

    if (xhr.status === 422 && body.error) {
      showMessage(body.message, true, body.error === 'expired' || body.error === 'bad_token');
      setButton('Unavailable', false);
      return;
    }

    if (xhr.status === 404) {
      showMessage('This link does not exist or has been removed.', true);
      setButton('Unavailable', false);
      return;
    }

    showMessage('Something went wrong. Please try again.', true);
    setButton(state.captchaRequired ? 'Get Link' : 'Continue', true);
  }

  /* ---------- Steps ---------- */

  function requestStep() {
    state.busy = true;
    setButton('Please wait…', false);
    showMessage('');

    var data = { alias: $visit.data('alias'), fingerprint: fingerprint };

    if (state.token) {
      data.token = state.token;
    }

    post($visit.data('info-url'), data).done(renderStep).fail(handleError);
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

  function renderStep(info) {
    state.busy = false;
    state.step = info.step;
    state.total = info.total_steps;
    state.token = info.token;
    state.captchaRequired = !!info.captcha_required;
    state.captchaResponse = '';

    $step.text('Step ' + info.step + ' of ' + info.total_steps);
    showStepAds(info.step, state.captchaRequired);
    $captcha.prop('hidden', true);
    setButton('Please wait…', false);
    runCountdown(Math.max(0, parseInt(info.dwell_seconds, 10) || 0));
  }

  function runCountdown(seconds) {
    clearInterval(timer);

    /* A small margin so the request never reaches the server before its own clock allows it. */
    var endsAt = Date.now() + seconds * 1000 + 300;

    function tick() {
      var left = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
      $seconds.text(left);

      if (Date.now() >= endsAt) {
        clearInterval(timer);
        $countdown.prop('hidden', true);
        countdownFinished();
      }
    }

    $countdown.prop('hidden', false);
    tick();
    timer = setInterval(tick, 250);
  }

  function countdownFinished() {
    if (!state.captchaRequired) {
      setButton('Continue', true);
      return;
    }

    setButton('Get Link', false);
    showCaptcha();
  }

  /* ---------- Captcha (Turnstile, hCaptcha or reCAPTCHA; all share the same render API) ---------- */

  var captchaWidget = null;

  function captchaApi() {
    return window[$visit.data('captcha-global')];
  }

  function renderCaptcha() {
    var api = captchaApi();

    if (captchaWidget !== null) {
      api.reset(captchaWidget);
      return;
    }

    captchaWidget = api.render($captcha.get(0), {
      sitekey: String($visit.data('captcha-site-key')),
      callback: function (response) {
        state.captchaResponse = response;
        setButton('Get Link', true);
      },
      'expired-callback': function () {
        state.captchaResponse = '';
        setButton('Get Link', false);
      },
    });
  }

  function showCaptcha() {
    $captcha.prop('hidden', false);

    if (!$visit.data('captcha-site-key')) {
      showMessage('Captcha is not configured on this site.', true);
      setButton('Get Link', true);
      return;
    }

    if (captchaApi() && typeof captchaApi().render === 'function') {
      renderCaptcha();
      return;
    }

    window.lcCaptchaReady = renderCaptcha;

    var script = document.createElement('script');
    script.src = $visit.data('captcha-script') + '&onload=lcCaptchaReady';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
  }

  function finish() {
    state.busy = true;
    setButton('Please wait…', false);
    showMessage('');

    post($visit.data('finish-url'), {
      alias: $visit.data('alias'),
      token: state.token,
      fingerprint: fingerprint,
      captcha_response: state.captchaResponse,
    }).done(function (body) {
      setButton('Opening…', false);
      window.location.href = body.destination;
    }).fail(function (xhr) {
      handleError(xhr);

      if (captchaWidget !== null && captchaApi()) {
        state.captchaResponse = '';
        captchaApi().reset(captchaWidget);
      }
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

  $('[data-report-open]').on('click', function () {
    $reportMessage.removeClass('visit-message-error').text('');

    if (dialog && typeof dialog.showModal === 'function') {
      dialog.showModal();
    }
  });

  $('[data-report-close]').on('click', function () {
    dialog.close();
  });

  $reportForm.on('submit', function (e) {
    e.preventDefault();

    var $submit = $reportForm.find('[type="submit"]').prop('disabled', true);

    post($visit.data('report-url'), {
      alias: $visit.data('alias'),
      reason: $reportForm.find('input[name="reason"]:checked').val(),
      detail: $('#report-detail').val(),
    }).done(function (body) {
      $reportMessage.removeClass('visit-message-error').text(body.message);
      $('#report-detail').val('');
      setTimeout(function () { dialog.close(); }, 1500);
    }).fail(function (xhr) {
      var body = xhr.responseJSON || {};
      var text = 'Your report could not be sent. Please try again.';

      if (xhr.status === 429) {
        text = 'Too many reports. Please try again in a minute.';
      } else if (body.errors) {
        text = body.errors[Object.keys(body.errors)[0]][0];
      }

      $reportMessage.addClass('visit-message-error').text(text);
    }).always(function () {
      $submit.prop('disabled', false);
    });
  });

  requestStep();
});
