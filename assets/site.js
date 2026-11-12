/* JS dùng chung cho mọi trang (jQuery) */
$(function () {
  var $w = $(window), $nav = $('#mainNav');

  $('#year').text(new Date().getFullYear());

  /* Đánh dấu menu của trang hiện tại */
  var page = $('body').data('page');
  $('#navMenu a[data-page="' + page + '"]').addClass('active');

  /* Thanh tiến độ cuộn + nút lên đầu trang */
  $('body').append('<div id="progress"></div><button id="toTop" type="button" aria-label="Back to top"><span></span></button>');
  $('#toTop').on('click', function () { $('html, body').animate({ scrollTop: 0 }, 500); });

  /* Navbar: nền xanh khi cuộn, menu mobile */
  function onScroll() {
    var y = $w.scrollTop(), h = $(document).height() - $w.height();
    $nav.toggleClass('affix', y > 100);
    $('#progress').css('width', (h > 0 ? y / h * 100 : 0) + '%');
    $('#toTop').toggleClass('show', y > 500);
  }
  $w.on('scroll', onScroll); onScroll();

  $('#navToggle').on('click', function () {
    $('#navMenu').toggleClass('hidden');
    $nav.toggleClass('open', !$('#navMenu').hasClass('hidden'));
  });

  /* Hiện dần khi cuộn tới (so le theo thứ tự trong hàng) */
  $('.reveal').each(function () {
    var i = $(this).parent().children('.reveal').index(this);
    $(this).css('transition-delay', (i % 3) * 0.12 + 's');
  });
  function reveal() {
    var bottom = $w.scrollTop() + $w.height() - 60;
    $('.reveal:not(.in)').each(function () {
      var $e = $(this);
      if ($e.offset().top < bottom) {
        $e.addClass('in');
        setTimeout(function () { $e.css('transition-delay', ''); }, 1200);
      }
    });
  }
  $w.on('scroll resize', reveal); reveal();

  /* Hiệu ứng gợn sóng khi bấm nút */
  $(document).on('click', '.btn-ripple', function (e) {
    var $b = $(this), off = $b.offset(), d = Math.max($b.outerWidth(), $b.outerHeight());
    $('<span class="ripple"></span>').css({
      width: d, height: d, left: e.pageX - off.left - d / 2, top: e.pageY - off.top - d / 2
    }).appendTo($b).on('animationend', function () { $(this).remove(); });
  });

  /* Mạng chấm nối nhau ở hero, phản ứng với con trỏ chuột */
  var cv = document.getElementById('particles-canvas');
  if (cv) {
    var ctx = cv.getContext('2d'), pts = [], W, H, mouse = { x: -999, y: -999 };
    var size = function () {
      var box = cv.parentNode;
      W = cv.width = box.clientWidth; H = cv.height = box.clientHeight;
      var n = Math.round(W * H / 14000); pts = [];
      for (var i = 0; i < n; i++) pts.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * 2.4, vy: (Math.random() - .5) * 2.4, r: Math.random() * 2 + 1 });
    };
    $('header.shorten').on('mousemove', function (e) {
      var o = $(cv).offset(); mouse.x = e.pageX - o.left; mouse.y = e.pageY - o.top;
    }).on('mouseleave', function () { mouse.x = mouse.y = -999; })
      .on('click', function () { for (var i = 0; i < 4; i++) pts.push({ x: mouse.x, y: mouse.y, vx: (Math.random() - .5) * 3, vy: (Math.random() - .5) * 3, r: Math.random() * 2 + 1 }); if (pts.length > 160) pts.splice(0, 4); });
    var tick = function () {
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < pts.length; i++) {
        var p = pts[i]; p.x += p.vx; p.y += p.vy;
        if (p.x < 0 || p.x > W) p.vx *= -1;
        if (p.y < 0 || p.y > H) p.vy *= -1;
        ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283); ctx.fillStyle = 'rgba(255,255,255,.5)'; ctx.fill();
        for (var j = i + 1; j < pts.length; j++) {
          var q = pts[j], d = Math.hypot(p.x - q.x, p.y - q.y);
          if (d < 110) { ctx.strokeStyle = 'rgba(255,255,255,' + (.35 * (1 - d / 110)) + ')'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke(); }
        }
        var dm = Math.hypot(p.x - mouse.x, p.y - mouse.y);
        if (dm < 160) { ctx.strokeStyle = 'rgba(228,251,74,' + (.7 * (1 - dm / 160)) + ')'; ctx.lineWidth = 1.2; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(mouse.x, mouse.y); ctx.stroke(); }
      }
      requestAnimationFrame(tick);
    };
    size(); tick(); $w.on('resize', size);
  }
});
