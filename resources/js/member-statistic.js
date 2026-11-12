import './jquery';
import './chart';

/* Statistic page: load the month totals as JSON, then fill the boxes, the chart and the daily table */
$(function () {
  var $page = $('#statistic');

  if (!$page.length) {
    return;
  }

  var date = String($page.data('date'));
  var parts = date.split('/');
  var year = parseInt(parts[2], 10);
  var month = parseInt(parts[1], 10);
  var lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();
  var today = new Date();
  var visibleDays = today.getUTCFullYear() === year && today.getUTCMonth() + 1 === month ? today.getUTCDate() : lastDay;

  function money(text) {
    return String(text).replace('$', '$ ');
  }

  function byDay(items) {
    var map = {};

    $.each(items, function (_, item) {
      map[parseInt(item.date.split('/')[0], 10)] = item.value;
    });

    return map;
  }

  function fail(message) {
    $('#statistic h3[id]').text('-');
    $('#detail-rows').html('<tr><td colspan="5" class="text-center text-danger"></td></tr>').find('td').text(message);
  }

  $.ajax({
    url: $page.data('url'),
    method: 'POST',
    data: { date: date },
    dataType: 'json',
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' },
  }).done(function (data) {
    $('#total_view').text(Number(data.total_view).toLocaleString('en-US'));
    $('#total_publisher_earn').text(money(data.total_publisher_earn));
    $('#total_ref_earn').text(money(data.total_ref_earn));
    $('#average_cpm').text(money(data.average_cpm));

    var views = byDay(data.pay_view_data);
    var earn = byDay(data.publisher_earn_data);
    var cpm = byDay(data.cpm_data);
    var ref = byDay(data.ref_data);
    var labels = [];
    var series = { views: [], earn: [], ref: [] };
    var rows = '';

    for (var day = 1; day <= lastDay; day++) {
      var shown = day <= visibleDays;
      labels.push(String(day));
      series.views.push(shown ? views[day] || 0 : null);
      series.earn.push(shown ? earn[day] || 0 : null);
      series.ref.push(shown ? ref[day] || 0 : null);

      if (views[day] !== undefined) {
        rows += '<tr><td class="text-center">' + ('0' + day).slice(-2) + '/' + ('0' + month).slice(-2) + '/' + year + '</td>'
          + '<td class="text-right">' + views[day].toLocaleString('en-US') + '</td>'
          + '<td class="text-right">' + earn[day].toFixed(3) + '</td>'
          + '<td class="text-right">' + cpm[day].toFixed(3) + '</td>'
          + '<td class="text-right">' + ref[day].toFixed(3) + '</td></tr>';
      }
    }

    $('#detail-rows').html(rows || '<tr><td colspan="5" class="text-center text-muted">No data for this month.</td></tr>');

    window.LcChart.draw(document.getElementById('income-chart'), {
      labels: labels,
      lines: [
        { label: 'Earnings ($)', color: '#00c0ef', axis: 'left', values: series.earn },
        { label: 'Referral earnings ($)', color: '#00a65a', axis: 'left', values: series.ref },
        { label: 'Views', color: '#f39c12', axis: 'right', values: series.views },
      ],
    });
  }).fail(function (xhr) {
    fail((xhr.responseJSON && xhr.responseJSON.info) || 'Unable to load the statistics. Please try again.');
  });
});
