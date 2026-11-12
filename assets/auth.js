/* JS cho 3 trang: đăng nhập, đăng ký, quên mật khẩu (demo, chưa nối backend) */
$(function () {
  var mail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function setErr($input, msg) {
    $input.closest('.af-group').addClass('has-error').find('.af-help').text(msg);
    return false;
  }
  function reset($form) {
    $form.find('.af-group').removeClass('has-error').find('.af-help').text('');
    $('#flash').empty();
  }
  function done(msg) { $('#flash').html('<div class="af-alert">' + msg + '</div>'); }

  /* Hiện / ẩn mật khẩu */
  $(document).on('click', '.af-eye', function () {
    var $i = $(this).siblings('input'), show = $i.attr('type') === 'password';
    $i.attr('type', show ? 'text' : 'password');
    $(this).toggleClass('on', show).attr('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
  });

  /* Xoá lỗi khi người dùng gõ lại */
  $(document).on('input change', '.af-group input', function () {
    $(this).closest('.af-group').removeClass('has-error').find('.af-help').text('');
  });

  /* ---- Đăng nhập ---- */
  $('#signin-form').on('submit', function (e) {
    e.preventDefault(); reset($(this));
    var ok = true;
    if (!$.trim($('#username').val())) ok = setErr($('#username'), 'Vui lòng nhập tên đăng nhập hoặc email.');
    if (!$('#password').val()) ok = setErr($('#password'), 'Vui lòng nhập mật khẩu.');
    if (ok) done('Bản demo: chức năng đăng nhập chưa được nối với backend.');
  });

  /* ---- Đăng ký ---- */
  $('#signup-form').on('submit', function (e) {
    e.preventDefault(); reset($(this));
    var ok = true, u = $.trim($('#username').val());
    if (!u) ok = setErr($('#username'), 'Vui lòng chọn tên đăng nhập.');
    else if (!/^[A-Za-z0-9_]{3,}$/.test(u)) ok = setErr($('#username'), 'Dùng ít nhất 3 chữ cái, số hoặc dấu gạch dưới.');
    if (!mail.test($.trim($('#email').val()))) ok = setErr($('#email'), 'Vui lòng nhập địa chỉ email hợp lệ.');
    if ($('#password').val().length < 6) ok = setErr($('#password'), 'Mật khẩu phải có ít nhất 6 ký tự.');
    if (ok) done('Bản demo: chức năng đăng ký chưa được nối với backend.');
  });

  /* Thanh đo độ mạnh mật khẩu */
  $('#signup-form #password').on('input', function () {
    var v = this.value, s = 0;
    if (v.length >= 8) s++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    var lv = v ? Math.max(1, s) : 0;
    $('#meter i').css({ width: lv * 25 + '%', background: ['#e5e7eb', '#e74c3c', '#f5a300', '#9fcb00', '#038018'][lv] });
    $('#meterText').text(['', 'Yếu', 'Trung bình', 'Khá', 'Mạnh'][lv]);
  });

  /* ---- Quên mật khẩu ---- */
  $('#forgot-form').on('submit', function (e) {
    e.preventDefault(); reset($(this));
    if (!mail.test($.trim($('#email').val()))) return setErr($('#email'), 'Vui lòng nhập địa chỉ email hợp lệ.');
    done('Bản demo: nếu email tồn tại, liên kết đặt lại mật khẩu sẽ được gửi.');
  });
});

/* Nút "Continue with Google" (demo) */
$(function () {
  $('#google-btn').on('click', function () {
    var $b = $(this).addClass('loading');
    setTimeout(function () {
      $b.removeClass('loading');
      $('#flash').html('<div class="af-alert">Bản demo: đăng nhập bằng Google chưa được kết nối.</div>');
    }, 700);
  });
});
