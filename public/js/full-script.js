/* LinkCash full page script: turns the links of a page into short links through the API. */
(function () {
  var base = String(window.linkcash_url || '').replace(/\/?$/, '/');
  var token = window.linkcash_api_token;
  var level = window.linkcash_advert;
  var only = window.linkcash_domains;
  var skip = window.linkcash_exclude_domains || [];

  if (!token || !base) {
    return;
  }

  function listed(host, list) {
    for (var i = 0; i < list.length; i++) {
      if (host === list[i] || host.slice(-list[i].length - 1) === '.' + list[i]) {
        return true;
      }
    }

    return false;
  }

  function wanted(a) {
    var host = a.hostname;

    if (!/^https?:$/.test(a.protocol) || host === location.hostname || a.href.indexOf(base) === 0) {
      return false;
    }

    return only ? listed(host, only) : !listed(host, skip);
  }

  function shorten(a) {
    var url = base + 'api?format=text&api=' + encodeURIComponent(token) + '&url=' + encodeURIComponent(a.href) + (level ? '&level=' + level : '');
    var req = new XMLHttpRequest();

    req.open('GET', url);
    req.onload = function () {
      if (req.status === 200 && /^https?:\/\//.test(req.responseText)) {
        a.href = req.responseText;
      }
    };
    req.send();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var links = document.getElementsByTagName('a');

    for (var i = 0; i < links.length; i++) {
      if (wanted(links[i])) {
        shorten(links[i]);
      }
    }
  });
})();
