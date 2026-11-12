import './images';
import './jquery';

/* JavaScript for the sign in, sign up and forgot password pages (demo, not connected to a backend) */
$(function () {
  var mail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function setError($input, message) {
    $input.closest('.form-group').addClass('has-error').find('.help-block').first().text(message);
    return false;
  }

  function resetForm($form) {
    $form.find('.form-group').removeClass('has-error').find('.help-block').text('');
    $('#flash').empty();
  }

  function showNotice(message) {
    $('#flash').html('<div class="alert alert-info">' + message + '</div>');
  }

  $(document).on('input change', '.form-group input', function () {
    $(this).closest('.form-group').removeClass('has-error').find('.help-block').first().text('');
  });

  $('#signin-form').on('submit', function (e) {
    e.preventDefault();
    resetForm($(this));
    var ok = true;
    if (!$.trim($('#username').val())) ok = setError($('#username'), 'Please enter your username or email.');
    if (!$('#password').val()) ok = setError($('#password'), 'Please enter your password.');
    if (ok) showNotice('Demo only: sign in is not connected to a backend yet.');
  });

  $('#signup-form').on('submit', function (e) {
    e.preventDefault();
    resetForm($(this));
    var ok = true, username = $.trim($('#username').val());
    if (!username) ok = setError($('#username'), 'Please choose a username.');
    else if (!/^[A-Za-z0-9_]{3,}$/.test(username)) ok = setError($('#username'), 'Use at least 3 letters, numbers or underscores.');
    if (!mail.test($.trim($('#email').val()))) ok = setError($('#email'), 'Please enter a valid email address.');
    if (!checkPasswordRules($('#password').val()).length) ok = setError($('#password'), 'Password must be at least 8 characters.');
    if (!$('#accept').is(':checked')) ok = setError($('#accept'), 'You must accept the terms.');
    if (ok) showNotice('Demo only: sign up is not connected to a backend yet.');
  });

  $('#forgotpassword-form').on('submit', function (e) {
    e.preventDefault();
    resetForm($(this));
    if (!mail.test($.trim($('#email').val()))) return setError($('#email'), 'Please enter a valid email address.');
    showNotice('Demo only: if the email exists, a password reset link would be sent.');
  });

  $(document).on('click', '.pw-toggle', function () {
    var $input = $(this).siblings('input');
    var show = $input.attr('type') === 'password';
    $input.attr('type', show ? 'text' : 'password');
    $(this).toggleClass('on', show).attr({ 'aria-pressed': show, 'aria-label': show ? 'Hide password' : 'Show password' });
  });

  function checkPasswordRules(value) {
    return {
      length: value.length >= 8,
      case: /[a-z]/.test(value) && /[A-Z]/.test(value),
      number: /\d/.test(value),
      symbol: /[^A-Za-z0-9]/.test(value)
    };
  }

  $('#signup-form #password').on('input', function () {
    var value = this.value, rules = checkPasswordRules(value), score = 0;
    $('#pw-rules li').each(function () {
      var met = rules[$(this).data('rule')];
      $(this).toggleClass('met', met);
      if (met) score++;
    });
    var level = value ? Math.max(1, score) : 0;
    $('.pw-meter i').css({ width: level * 25 + '%', background: ['#e5e7eb', '#dd4b39', '#f39c12', '#00a65a', '#008d4c'][level] });
    $('#pw-text').text(['', 'Weak', 'Fair', 'Good', 'Strong'][level]);
  });
});
