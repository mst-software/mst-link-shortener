import './jquery';

/* JavaScript for the publisher rates page: country filter and earnings calculator */
$(function () {
  /* ---- Filter countries ---- */
  $('#rateSearch').on('input', function () {
    var q = $.trim(this.value).toLowerCase(), shown = 0;
    $('#rateTable tbody tr').not('#noRate').each(function () {
      var m = $(this).children().first().text().toLowerCase().indexOf(q) > -1;
      $(this).toggle(m); if (m) shown++;
    });
    $('#noRate').toggle(shown === 0);
  });

  /* ---- Earnings calculator ---- */
  var rates = [];
  $('#rateTable tbody tr').not('#noRate').each(function () {
    var $c = $(this).children();
    rates.push({ name: $c.eq(0).text(), v: [+$c.eq(1).data('v'), +$c.eq(2).data('v'), +$c.eq(3).data('v')] });
  });
  $.each(rates, function (i, r) { $('#calcCountry').append($('<option>').val(i).text(r.name)); });

  function money(v) { return '$' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

  var shown = 0;
  function calc() {
    var r = rates[+$('#calcCountry').val()], views = +$('#calcViews').val();
    $('#viewsLabel').text(views.toLocaleString('en-US'));
    var total = views / 1000 * r.v[+$('#calcLevel').val()];
    $({ n: shown }).stop(true).animate({ n: total }, {
      duration: 400,
      step: function (v) { $('#calcResult').text(money(v)); },
      complete: function () { shown = total; $('#calcResult').text(money(total)); }
    });
    shown = total;
  }
  $('#calcCountry, #calcLevel').on('change', calc);
  $('#calcViews').on('input', calc);
  calc();
});
