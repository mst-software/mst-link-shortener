import './jquery';

/* JavaScript for the home page (demo, not connected to a backend) */
$(function () {
  /* ---- Count up the stats when scrolled into view ---- */
  var counted = false;
  function countUp() {
    var $first = $('.stat-num').first();
    if (counted || $first.offset().top > $(window).scrollTop() + $(window).height() - 60) return;
    counted = true;
    $('.stat-num').each(function () {
      var $el = $(this), target = +$el.data('target');
      $({ n: 0 }).animate({ n: target }, {
        duration: 1800,
        step: function (v) { $el.text(Math.floor(v).toLocaleString('en-US')); },
        complete: function () { $el.text(target.toLocaleString('en-US')); }
      });
    });
  }
  $(window).on('scroll resize', countUp); countUp();

  /* ---- Shorten a link (demo) ---- */
  function validUrl(v) {
    try { return new URL(/^https?:\/\//i.test(v) ? v : 'http://' + v).hostname.indexOf('.') > 0; }
    catch (e) { return false; }
  }
  function alias(n) {
    var c = 'abcdefghijkmnpqrstuvwxyz23456789', s = '';
    for (var i = 0; i < n; i++) s += c.charAt(Math.floor(Math.random() * c.length));
    return s;
  }
  $('#shorten').on('submit', function (e) {
    e.preventDefault();
    var v = $.trim($('#url').val()), $r = $('.add-link-result').empty();
    if (!v) return $r.html('<span class="err">Please enter the URL you want to shorten.</span>');
    if (!validUrl(v)) return $r.html('<span class="err">Invalid URL.</span>');
    var short = 'https://lnkcash.com/' + alias(6);
    $r.html('<div class="result"><input readonly value="' + short + '"><button type="button" class="copy">Copy</button></div>');
  });
  $(document).on('click', '.add-link-result .copy', function () {
    var $b = $(this), $i = $b.siblings('input').select();
    var done = function () { $b.text('Copied!'); setTimeout(function () { $b.text('Copy'); }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText($i.val()).then(done, done); else { document.execCommand('copy'); done(); }
  });

  /* ---- Contact form (demo) ---- */
  $('#contact-form').on('submit', function (e) {
    e.preventDefault();
    var f = this, ok = true;
    $('#contact-form .text-danger').text('');
    function err(el, msg) { $(el).closest('.form-group').find('.text-danger').text(msg); ok = false; }
    if (!$.trim(f.name.value)) err(f.name, 'Please enter your name.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($.trim(f.email.value))) err(f.email, 'Please enter a valid email.');
    if (!$.trim(f.subject.value)) err(f.subject, 'Please enter a subject.');
    if (!$.trim(f.message.value)) err(f.message, 'Please enter your message.');
    if (!f.accept.checked) err(f.accept, 'You must agree before sending.');
    if (!ok) return;
    $('#success').html('<div class="text-g font-bold">Your message has been sent, we will reply soon!</div>');
    f.reset();
  });
});
