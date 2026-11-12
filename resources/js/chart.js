/*
 * Minimal canvas line chart.
 *
 * window.LcChart.draw(canvas, {
 *   labels: ['1', '2', ...],
 *   lines: [{ label: 'Earnings ($)', color: '#00c0ef', axis: 'left', values: [0.1, null, 0.3] }]
 * })
 *
 * Lines on axis "left" share the left scale, lines on axis "right" share the right scale.
 * A null value leaves a gap. The chart redraws itself when the canvas is resized.
 */
(function () {
  var PAD = { top: 28, right: 56, bottom: 28, left: 56 };

  function niceMax(value) {
    if (!(value > 0)) {
      return 1;
    }

    var pow = Math.pow(10, Math.floor(Math.log10(value)));
    var n = value / pow;
    var step = n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10;

    return step * pow;
  }

  function maxOf(lines, axis) {
    var max = 0;

    lines.forEach(function (line) {
      if ((line.axis || 'left') !== axis) {
        return;
      }

      line.values.forEach(function (v) {
        if (v !== null && v > max) {
          max = v;
        }
      });
    });

    return niceMax(max);
  }

  function fmt(value, max) {
    return max < 10 ? value.toFixed(3) : String(Math.round(value));
  }

  function render(canvas, series, hoverIndex) {
    var dpr = window.devicePixelRatio || 1;
    var width = canvas.clientWidth;
    var height = canvas.clientHeight;

    if (!width || !height) {
      return;
    }

    canvas.width = width * dpr;
    canvas.height = height * dpr;

    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);
    ctx.font = '11px "Source Sans Pro", Arial, sans-serif';

    var lines = series.lines || [];
    var labels = series.labels || [];
    var plotW = width - PAD.left - PAD.right;
    var plotH = height - PAD.top - PAD.bottom;
    var max = { left: maxOf(lines, 'left'), right: maxOf(lines, 'right') };
    var hasRight = lines.some(function (l) { return l.axis === 'right'; });
    var count = Math.max(labels.length, 1);

    function x(i) {
      return PAD.left + (count === 1 ? plotW / 2 : (plotW * i) / (count - 1));
    }

    function y(v, axis) {
      return PAD.top + plotH - (v / max[axis]) * plotH;
    }

    /* Grid and axes */
    ctx.strokeStyle = '#e5e7eb';
    ctx.fillStyle = '#777';
    ctx.lineWidth = 1;

    for (var g = 0; g <= 4; g++) {
      var gy = Math.round(PAD.top + (plotH * g) / 4) + 0.5;
      ctx.beginPath();
      ctx.moveTo(PAD.left, gy);
      ctx.lineTo(PAD.left + plotW, gy);
      ctx.stroke();

      ctx.textAlign = 'right';
      ctx.fillText(fmt(max.left * (1 - g / 4), max.left), PAD.left - 6, gy + 4);

      if (hasRight) {
        ctx.textAlign = 'left';
        ctx.fillText(fmt(max.right * (1 - g / 4), max.right), PAD.left + plotW + 6, gy + 4);
      }
    }

    var every = Math.ceil(count / Math.max(Math.floor(plotW / 28), 1));
    ctx.textAlign = 'center';

    labels.forEach(function (label, i) {
      if (i % every === 0) {
        ctx.fillText(label, x(i), height - PAD.bottom + 16);
      }
    });

    /* Lines */
    lines.forEach(function (line) {
      var axis = line.axis || 'left';
      ctx.strokeStyle = line.color;
      ctx.fillStyle = line.color;
      ctx.lineWidth = 2;
      ctx.beginPath();

      var open = false;

      line.values.forEach(function (v, i) {
        if (v === null) {
          open = false;

          return;
        }

        if (open) {
          ctx.lineTo(x(i), y(v, axis));
        } else {
          ctx.moveTo(x(i), y(v, axis));
          open = true;
        }
      });

      ctx.stroke();

      line.values.forEach(function (v, i) {
        if (v !== null) {
          ctx.beginPath();
          ctx.arc(x(i), y(v, axis), 2.5, 0, Math.PI * 2);
          ctx.fill();
        }
      });
    });

    /* Legend */
    var lx = PAD.left;

    lines.forEach(function (line) {
      ctx.fillStyle = line.color;
      ctx.fillRect(lx, 8, 10, 10);
      ctx.fillStyle = '#333';
      ctx.textAlign = 'left';
      ctx.fillText(line.label, lx + 14, 17);
      lx += 14 + ctx.measureText(line.label).width + 16;
    });

    /* Hover tooltip */
    if (hoverIndex !== null && hoverIndex >= 0 && hoverIndex < count) {
      var hx = x(hoverIndex);
      ctx.strokeStyle = '#bbb';
      ctx.beginPath();
      ctx.moveTo(hx, PAD.top);
      ctx.lineTo(hx, PAD.top + plotH);
      ctx.stroke();

      var rows = [labels[hoverIndex]].concat(lines.map(function (line) {
        var v = line.values[hoverIndex];

        return line.label + ': ' + (v === null ? '-' : fmt(v, 0 + (line.axis === 'right' ? 100 : 1)));
      }));
      var boxW = Math.max.apply(null, rows.map(function (r) { return ctx.measureText(r).width; })) + 16;
      var boxH = rows.length * 16 + 8;
      var bx = hx + 10 + boxW > width ? hx - 10 - boxW : hx + 10;

      ctx.fillStyle = 'rgba(34,45,50,0.92)';
      ctx.fillRect(bx, PAD.top + 4, boxW, boxH);
      ctx.fillStyle = '#fff';
      ctx.textAlign = 'left';
      rows.forEach(function (r, i) {
        ctx.fillText(r, bx + 8, PAD.top + 20 + i * 16);
      });
    }
  }

  window.LcChart = {
    draw: function (canvas, series) {
      function redraw(hover) {
        render(canvas, series, hover);
      }

      redraw(null);

      if (canvas.lcBound) {
        canvas.lcSeries.lines = series.lines;
        canvas.lcSeries.labels = series.labels;

        return;
      }

      canvas.lcBound = true;
      canvas.lcSeries = series;

      window.addEventListener('resize', function () {
        render(canvas, canvas.lcSeries, null);
      });

      canvas.addEventListener('mousemove', function (e) {
        var rect = canvas.getBoundingClientRect();
        var count = Math.max(canvas.lcSeries.labels.length, 1);
        var plotW = rect.width - PAD.left - PAD.right;
        var index = count === 1 ? 0 : Math.round(((e.clientX - rect.left - PAD.left) / plotW) * (count - 1));

        render(canvas, canvas.lcSeries, Math.min(Math.max(index, 0), count - 1));
      });

      canvas.addEventListener('mouseleave', function () {
        render(canvas, canvas.lcSeries, null);
      });
    },
  };
})();
