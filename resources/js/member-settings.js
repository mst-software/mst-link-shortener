import './jquery';

/* Profile page: show only the detail fields of the selected payment method */
$(function () {
  function showSelectedMethod() {
    var method = $('input[name="payout_method"]:checked').val();

    $('.payout-details').each(function () {
      $(this).toggle($(this).data('method') === method);
    });
  }

  $('input[name="payout_method"]').on('change', showSelectedMethod);
  showSelectedMethod();
});
