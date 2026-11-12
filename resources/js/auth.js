import './images';
import './jquery';
import './password-meter';

/* JavaScript for the sign in, sign up, forgot and reset password pages: client-side hints only, the server validates */
$(function () {
  var mail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function setError($input, message) {
    $input.closest('.form-group').addClass('has-error').find('.help-block').first().text(message);
    return false;
  }

  function resetForm($form) {
    $form.find('.form-group').removeClass('has-error').find('.help-block').text('');
  }

  $(document).on('input change', '.form-group input', function () {
    $(this).closest('.form-group').removeClass('has-error').find('.help-block').first().text('');
  });

  $('#signin-form').on('submit', function (e) {
    resetForm($(this));
    var ok = true;
    if (!$.trim($('#username').val())) ok = setError($('#username'), 'Please enter your username or email.');
    if (!$('#password').val()) ok = setError($('#password'), 'Please enter your password.');
    if (!ok) e.preventDefault();
  });

  $('#signup-form').on('submit', function (e) {
    resetForm($(this));
    var ok = true, username = $.trim($('#username').val());
    if (!username) ok = setError($('#username'), 'Please choose a username.');
    else if (!/^[A-Za-z0-9_]{3,32}$/.test(username)) ok = setError($('#username'), 'Use 3 to 32 letters, numbers or underscores.');
    if (!mail.test($.trim($('#email').val()))) ok = setError($('#email'), 'Please enter a valid email address.');
    if ($('#password').val().length < 8) ok = setError($('#password'), 'Password must be at least 8 characters.');
    if (!$('#accept').is(':checked')) ok = setError($('#accept'), 'You must accept the terms.');
    if (!ok) e.preventDefault();
  });

  $('#forgotpassword-form').on('submit', function (e) {
    resetForm($(this));
    if (!mail.test($.trim($('#email').val()))) {
      setError($('#email'), 'Please enter a valid email address.');
      e.preventDefault();
    }
  });

  $('#resetpassword-form').on('submit', function (e) {
    resetForm($(this));
    var ok = true;
    if (!mail.test($.trim($('#email').val()))) ok = setError($('#email'), 'Please enter a valid email address.');
    if ($('#password').val().length < 8) ok = setError($('#password'), 'Password must be at least 8 characters.');
    if (!ok) e.preventDefault();
  });
});
