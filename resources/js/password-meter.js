import './jquery';

/* Shared password helpers: show/hide toggle and the strength checklist with green ticks */
$(function () {
  function checkPasswordRules(value) {
    return {
      length: value.length >= 8,
      case: /[a-z]/.test(value) && /[A-Z]/.test(value),
      number: /\d/.test(value),
      symbol: /[^A-Za-z0-9]/.test(value)
    };
  }

  $(document).on('click', '.pw-toggle', function () {
    var $input = $(this).siblings('input');
    var show = $input.attr('type') === 'password';
    $input.attr('type', show ? 'text' : 'password');
    $(this).toggleClass('on', show).attr({ 'aria-pressed': show, 'aria-label': show ? 'Hide password' : 'Show password' });
  });

  $(document).on('input', '.form-group:has(.pw-rules) input[name="password"]', function () {
    var value = this.value, rules = checkPasswordRules(value), score = 0;
    var $group = $(this).closest('.form-group');

    $group.find('.pw-rules li').each(function () {
      var met = rules[$(this).data('rule')];
      $(this).toggleClass('met', met);
      if (met) score++;
    });

    var level = value ? Math.max(1, score) : 0;
    $group.find('.pw-meter i').css({ width: level * 25 + '%', background: ['#e5e7eb', '#dd4b39', '#f39c12', '#00a65a', '#008d4c'][level] });
    $group.find('.pw-text').text(['', 'Weak', 'Fair', 'Good', 'Strong'][level]);
  });
});
