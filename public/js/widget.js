/*! LinkCash code widget. Plain JS, no dependencies. Embed:
 *  <script async src="https://YOUR-DOMAIN/js/widget.js" data-campaign="WIDGET_KEY"></script>
 */
(function () {
  'use strict';

  var script = document.currentScript || document.querySelector('script[data-campaign][src*="widget.js"]');
  if (!script || window.__linkcashWidget) { return; }
  window.__linkcashWidget = true;

  var key = script.getAttribute('data-campaign');
  var base = new URL(script.src, location.href).origin;
  var storeKey = 'linkcash_widget_' + key;
  if (!key) { return; }

  var HINT = 'Please click any link and scroll back to this position to get the CODE';
  var pageLoadId = randomId();
  var timer = null;

  function randomId() {
    var bytes = new Uint8Array(12);
    (window.crypto || window.msCrypto).getRandomValues(bytes);
    return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }

  function fingerprint() {
    var raw = [navigator.language, screen.width + 'x' + screen.height, screen.colorDepth,
      new Date().getTimezoneOffset(), navigator.hardwareConcurrency || 0].join('|');
    var h = 5381;
    for (var i = 0; i < raw.length; i++) { h = ((h << 5) + h + raw.charCodeAt(i)) >>> 0; }
    return ('00000000' + h.toString(16)).slice(-8) + ('00000000' + raw.length.toString(16)).slice(-8);
  }

  function storage() {
    try {
      localStorage.setItem(storeKey + '_t', '1');
      localStorage.removeItem(storeKey + '_t');
      return true;
    } catch (e) { return false; }
  }

  function getToken() { try { return localStorage.getItem(storeKey); } catch (e) { return null; } }
  function setToken(t) { try { localStorage.setItem(storeKey, t); } catch (e) { /* ignored */ } }
  function clearToken() { try { localStorage.removeItem(storeKey); } catch (e) { /* ignored */ } }

  var CSS = '.lcw{box-sizing:border-box;max-width:560px;margin:24px auto;padding:0 12px;text-align:center;' +
    'font:600 15px/1.4 Arial,Helvetica,sans-serif;color:#333}' +
    '.lcw *{box-sizing:border-box}' +
    '.lcw-btn{display:inline-block;max-width:100%;padding:11px 22px;border:0;border-radius:8px;background:#e8202f;' +
    'color:#fff;font:inherit;font-weight:700;cursor:pointer;word-wrap:break-word}' +
    '.lcw-btn[disabled]{cursor:default;opacity:.95}' +
    '.lcw-code{display:inline-flex;align-items:center;gap:10px;max-width:100%}' +
    '.lcw-copy{width:40px;height:40px;padding:0;border:0;border-radius:8px;background:#e8202f;color:#fff;cursor:pointer}' +
    '.lcw-copy svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2}' +
    '.lcw-msg{margin-top:10px;font-weight:400;font-size:14px;color:#555}';

  var root = document.createElement('div');
  root.className = 'lcw';
  var style = document.createElement('style');
  style.textContent = CSS;
  root.appendChild(style);
  var main = document.createElement('div');
  var msg = document.createElement('div');
  msg.className = 'lcw-msg';
  root.appendChild(main);
  root.appendChild(msg);
  (document.body || document.documentElement).appendChild(root);

  function clear() {
    if (timer) { clearInterval(timer); timer = null; }
    while (main.firstChild) { main.removeChild(main.firstChild); }
  }

  function button(label, onClick) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'lcw-btn';
    b.textContent = label;
    if (onClick) { b.addEventListener('click', onClick); } else { b.disabled = true; }
    return b;
  }

  function note(text) { msg.textContent = text || ''; }

  function post(path, data) {
    return fetch(base + '/widget/' + path, {
      method: 'POST',
      mode: 'cors',
      credentials: 'omit',
      headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
      body: JSON.stringify(data)
    }).then(function (res) {
      return res.json().then(function (body) { return { status: res.status, body: body }; });
    });
  }

  function fail() {
    clear();
    main.appendChild(button('Try again', init));
    note('Something went wrong. Please try again.');
  }

  function unavailable() {
    clear();
    clearToken();
    note('This campaign is not available right now.');
  }

  function showStart() {
    clear();
    note('');
    main.appendChild(button('GET CODE', function () { start(null); }));
  }

  function countdown(seconds, label, done) {
    clear();
    var b = button('');
    main.appendChild(b);
    var deadline = Date.now() + seconds * 1000;

    function tick() {
      var left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
      b.textContent = label(left);
      if (left <= 0) {
        clearInterval(timer);
        timer = null;
        done();
      }
    }

    tick();
    if (seconds > 0) { timer = setInterval(tick, 250); }
  }

  function showCode(code) {
    clear();
    note('');
    var wrap = document.createElement('div');
    wrap.className = 'lcw-code';
    wrap.appendChild(button('Promo code: ' + code));
    var copy = document.createElement('button');
    copy.type = 'button';
    copy.className = 'lcw-copy';
    copy.title = 'Copy code';
    copy.setAttribute('aria-label', 'Copy code');
    copy.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/>' +
      '<path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>';
    copy.addEventListener('click', function () { copyText(code); });
    wrap.appendChild(copy);
    main.appendChild(wrap);
  }

  function copyText(text) {
    var done = function () { note('Code copied'); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, legacy);
    } else { legacy(); }

    function legacy() {
      var area = document.createElement('textarea');
      area.value = text;
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();
      try { document.execCommand('copy'); done(); } catch (e) { /* ignored */ }
      document.body.removeChild(area);
    }
  }

  function handleError(res) {
    var error = res.body && res.body.error;
    if (error === 'campaign_unavailable') { return unavailable(); }
    if (error === 'bad_token') { clearToken(); return showStart(); }
    fail();
  }

  /** Render the stage the server reports; startedHere is true on the page that began the attempt. */
  function apply(data, startedHere) {
    if (data.stage === 3 && data.code) { return showCode(data.code); }

    if (data.stage === 2) {
      note('');
      return countdown(data.remaining_seconds, function (n) { return 'Get code after ' + n + 's (2/2)'; }, getCode);
    }

    countdown(data.remaining_seconds, function (n) { return 'Please wait ' + n + 's (1/2)'; }, function () {
      if (startedHere) {
        clear();
        main.appendChild(button('Please wait... (1/2)'));
        note(HINT);
        return;
      }
      progress();
    });
    note(HINT);
  }

  function start(token) {
    var body = { campaign: key, referrer: document.referrer, page_load_id: pageLoadId, fingerprint: fingerprint() };
    if (token) { body.attempt_token = token; }

    post('start', body).then(function (res) {
      if (res.status !== 200) { return handleError(res); }
      var data = res.body;
      var startedHere = !token || data.attempt_token !== token;
      setToken(data.attempt_token);
      apply(data, startedHere);
    }, fail);
  }

  function progress() {
    post('progress', { attempt_token: getToken(), page_load_id: pageLoadId }).then(function (res) {
      if (res.status === 422 && res.body.remaining_seconds) {
        return countdown(res.body.remaining_seconds, function (n) { return 'Please wait ' + n + 's (1/2)'; }, progress);
      }
      if (res.status !== 200) { return handleError(res); }
      apply({ stage: res.body.stage, remaining_seconds: res.body.remaining_seconds }, false);
    }, fail);
  }

  function getCode() {
    post('code', { attempt_token: getToken() }).then(function (res) {
      if (res.status === 422 && res.body.remaining_seconds) {
        return countdown(res.body.remaining_seconds, function (n) { return 'Get code after ' + n + 's (2/2)'; }, getCode);
      }
      if (res.status !== 200) { return handleError(res); }
      showCode(res.body.code);
    }, fail);
  }

  function init() {
    if (!storage()) {
      clear();
      note('Please enable site storage to get the code');
      return;
    }

    var token = getToken();
    if (token) { start(token); } else { showStart(); }
  }

  init();
})();
