/*!
 * PSCharts — tiny dependency-free SVG charts (line/area, bars, donut, sparkline).
 * Theme-aware (reads CSS variables), responsive (ResizeObserver) and touch friendly.
 */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var registry = [];
  var uid = 0;

  function cssVar(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback || '#888';
  }
  function resolveColor(c) {
    if (typeof c === 'string' && c.indexOf('var(') === 0) {
      return cssVar(c.slice(4, c.indexOf(')')).trim(), '#888');
    }
    return c;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
  }
  /* 1.2K / 3.4M style numbers. `precise` keeps one decimal at every size (axis ticks). */
  function compact(n, precise) {
    n = Number(n) || 0;
    var a = Math.abs(n);
    var d = function (x, big) { return x.toFixed(!precise && big ? 0 : 1).replace(/\.0$/, ''); };
    if (a >= 1e9) return d(n / 1e9, a >= 1e10) + 'B';
    if (a >= 1e6) return d(n / 1e6, a >= 1e7) + 'M';
    if (a >= 1e3) return d(n / 1e3, a >= 1e4) + 'K';
    return String(Math.round(n * 10) / 10);
  }
  function full(n) { return (Number(n) || 0).toLocaleString(); }
  function svg(tag, attrs, parent) {
    var e = document.createElementNS(NS, tag);
    if (attrs) for (var k in attrs) e.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(e);
    return e;
  }
  function niceMax(v) {
    if (v <= 0) return 4;
    var exp = Math.pow(10, Math.floor(Math.log10(v)));
    var f = v / exp;
    var nice = f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10;
    return nice * exp;
  }
  function shortDate(s) {
    var d = new Date(s + 'T00:00:00');
    if (isNaN(d)) return s;
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }
  function hasData(series) {
    return series.some(function (s) { return s.data.some(function (v) { return Number(v) > 0; }); });
  }
  function emptyState(host, msg) {
    host.innerHTML = '<div class="chart-empty">' + esc(msg || 'No data yet') + '</div>';
  }
  function tooltipFor(host) {
    var tip = host.querySelector('.chart-tip');
    if (!tip) {
      tip = document.createElement('div');
      tip.className = 'chart-tip';
      host.appendChild(tip);
    }
    return tip;
  }
  function placeTip(host, tip, x, y) {
    var w = host.clientWidth;
    var tw = tip.offsetWidth;
    var left = x + 14;
    if (left + tw > w) left = x - tw - 14;
    if (left < 0) left = 0;
    tip.style.left = left + 'px';
    tip.style.top = Math.max(0, y - 10) + 'px';
  }

  /* monotone cubic path through points [[x,y],...] */
  function smoothPath(pts) {
    if (pts.length < 2) return pts.length ? 'M' + pts[0][0] + ',' + pts[0][1] : '';
    var n = pts.length, dx = [], dy = [], m = [], t = [], i;
    for (i = 0; i < n - 1; i++) {
      dx[i] = pts[i + 1][0] - pts[i][0];
      dy[i] = pts[i + 1][1] - pts[i][1];
      m[i] = dy[i] / (dx[i] || 1);
    }
    t[0] = m[0]; t[n - 1] = m[n - 2];
    for (i = 1; i < n - 1; i++) t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2;
    for (i = 0; i < n - 1; i++) {
      if (m[i] === 0) { t[i] = 0; t[i + 1] = 0; continue; }
      var a = t[i] / m[i], b = t[i + 1] / m[i], s = a * a + b * b;
      if (s > 9) { var k = 3 / Math.sqrt(s); t[i] = k * a * m[i]; t[i + 1] = k * b * m[i]; }
    }
    var d = 'M' + pts[0][0] + ',' + pts[0][1];
    for (i = 0; i < n - 1; i++) {
      var h = dx[i] / 3;
      d += 'C' + (pts[i][0] + h) + ',' + (pts[i][1] + t[i] * h) + ' ' +
           (pts[i + 1][0] - h) + ',' + (pts[i + 1][1] - t[i + 1] * h) + ' ' +
           pts[i + 1][0] + ',' + pts[i + 1][1];
    }
    return d;
  }

  /* ----------------------------------------------------------------- line */
  function drawLine(host, o) {
    var series = o.series || [];
    var labels = o.labels || [];
    if (!labels.length || !hasData(series)) return emptyState(host, o.empty);
    host.innerHTML = '';
    var W = Math.max(host.clientWidth, 200);
    var H = o.height || (W < 480 ? 200 : 260);
    var padL = 40, padR = 12, padT = 12, padB = 26;
    var iw = W - padL - padR, ih = H - padT - padB;
    var max = 0;
    series.forEach(function (s) { s.data.forEach(function (v) { if (v > max) max = v; }); });
    max = niceMax(max);
    var n = labels.length;
    var x = function (i) { return padL + (n === 1 ? iw / 2 : (i * iw) / (n - 1)); };
    var y = function (v) { return padT + ih - (v / max) * ih; };

    var root = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, height: H, role: 'img' }, host);
    if (o.ariaLabel) root.setAttribute('aria-label', o.ariaLabel);
    var defs = svg('defs', null, root);

    var ticks = 4;
    for (var g = 0; g <= ticks; g++) {
      var gv = (max / ticks) * g, gy = y(gv);
      svg('line', { x1: padL, x2: W - padR, y1: gy, y2: gy, 'class': 'gridline' }, root);
      var tl = svg('text', { x: padL - 8, y: gy + 4, 'text-anchor': 'end', 'class': 'ax' }, root);
      tl.textContent = compact(gv, true);
    }
    var step = Math.max(1, Math.ceil(n / (W < 480 ? 5 : 8)));
    for (var i = 0; i < n; i += step) {
      var xl = svg('text', { x: x(i), y: H - 6, 'text-anchor': i === 0 ? 'start' : 'middle', 'class': 'ax' }, root);
      xl.textContent = o.dates === false ? labels[i] : shortDate(labels[i]);
    }

    series.forEach(function (s, si) {
      var color = resolveColor(s.color);
      var pts = s.data.map(function (v, i) { return [x(i), y(v)]; });
      var d = smoothPath(pts);
      if (o.area !== false && (si === 0 || o.areaAll)) {
        var id = 'psg' + (++uid);
        var lg = svg('linearGradient', { id: id, x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
        svg('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': .28 }, lg);
        svg('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': 0 }, lg);
        svg('path', { d: d + 'L' + pts[pts.length - 1][0] + ',' + (padT + ih) + 'L' + pts[0][0] + ',' + (padT + ih) + 'Z', fill: 'url(#' + id + ')' }, root);
      }
      svg('path', { d: d, fill: 'none', stroke: color, 'stroke-width': 2.25, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, root);
      if (n <= 16) pts.forEach(function (p) { svg('circle', { cx: p[0], cy: p[1], r: 2.8, fill: color }, root); });
    });

    var guide = svg('line', { y1: padT, y2: padT + ih, stroke: cssVar('--border-strong'), 'stroke-width': 1, opacity: 0 }, root);
    var dots = series.map(function (s) {
      return svg('circle', { r: 4.5, fill: resolveColor(s.color), stroke: cssVar('--surface'), 'stroke-width': 2, opacity: 0 }, root);
    });
    var tip = tooltipFor(host);
    var overlay = svg('rect', { x: padL, y: padT, width: iw, height: ih, fill: 'transparent', style: 'touch-action:pan-y' }, root);

    function move(ev) {
      var r = root.getBoundingClientRect();
      var cx = ((ev.clientX - r.left) / r.width) * W;
      var idx = n === 1 ? 0 : Math.round(((cx - padL) / iw) * (n - 1));
      idx = Math.max(0, Math.min(n - 1, idx));
      guide.setAttribute('x1', x(idx)); guide.setAttribute('x2', x(idx)); guide.setAttribute('opacity', 1);
      var rows = '';
      series.forEach(function (s, si) {
        dots[si].setAttribute('cx', x(idx)); dots[si].setAttribute('cy', y(s.data[idx])); dots[si].setAttribute('opacity', 1);
        rows += '<div class="r"><span><i style="background:' + resolveColor(s.color) + '"></i>' + esc(s.name) + '</span><b>' + full(s.data[idx]) + '</b></div>';
      });
      tip.innerHTML = '<div class="t">' + esc(o.dates === false ? labels[idx] : shortDate(labels[idx])) + '</div>' + rows;
      tip.classList.add('show');
      var hr = host.getBoundingClientRect();
      placeTip(host, tip, ev.clientX - hr.left, ev.clientY - hr.top);
    }
    function leave() {
      guide.setAttribute('opacity', 0); dots.forEach(function (d) { d.setAttribute('opacity', 0); }); tip.classList.remove('show');
    }
    overlay.addEventListener('pointermove', move);
    overlay.addEventListener('pointerdown', move);
    overlay.addEventListener('pointerleave', leave);
  }

  /* ------------------------------------------------------------------ bar */
  function drawBars(host, o) {
    var series = o.series || [];
    var labels = o.labels || [];
    if (!labels.length || !hasData(series)) return emptyState(host, o.empty);
    host.innerHTML = '';
    var W = Math.max(host.clientWidth, 200);
    var H = o.height || (W < 480 ? 180 : 220);
    var padL = 34, padR = 8, padT = 10, padB = 26;
    var iw = W - padL - padR, ih = H - padT - padB;
    var max = 0;
    labels.forEach(function (_, i) {
      var tot = 0;
      series.forEach(function (s) { tot += s.data[i] || 0; });
      if (tot > max) max = tot;
    });
    /* counts: keep whole-number ticks (4 steps) */
    var barTicks = 4;
    if (o.integer !== false && max <= 4) { max = Math.max(max, 1); barTicks = Math.ceil(max); max = barTicks; }
    else if (o.integer !== false && max <= 400) { max = Math.ceil(max / 4) * 4; }
    else { max = niceMax(max); }
    var n = labels.length;
    var slot = iw / n;
    var bw = Math.max(2, Math.min(28, slot * 0.66));
    var y = function (v) { return padT + ih - (v / max) * ih; };
    var root = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, height: H, role: 'img' }, host);
    for (var g = 0; g <= barTicks; g++) {
      var gv = (max / barTicks) * g, gy = y(gv);
      svg('line', { x1: padL, x2: W - padR, y1: gy, y2: gy, 'class': 'gridline' }, root);
      var tl = svg('text', { x: padL - 6, y: gy + 4, 'text-anchor': 'end', 'class': 'ax' }, root);
      tl.textContent = compact(gv, true);
    }
    var step = Math.max(1, Math.ceil(n / (W < 480 ? 5 : 8)));
    var tip = tooltipFor(host);
    labels.forEach(function (lab, i) {
      var cx = padL + slot * i + slot / 2;
      var acc = 0;
      series.forEach(function (s) {
        var v = s.data[i] || 0;
        if (v <= 0) return;
        var top = y(acc + v), h = y(acc) - top;
        svg('rect', { x: cx - bw / 2, y: top, width: bw, height: Math.max(h, 1), rx: Math.min(4, bw / 2), fill: resolveColor(s.color) }, root);
        acc += v;
      });
      if (i % step === 0) {
        var xl = svg('text', { x: cx, y: H - 6, 'text-anchor': 'middle', 'class': 'ax' }, root);
        xl.textContent = o.dates === false ? lab : shortDate(lab);
      }
      var hit = svg('rect', { x: padL + slot * i, y: padT, width: slot, height: ih, fill: 'transparent' }, root);
      var show = function (ev) {
        var rows = '';
        series.forEach(function (s) {
          rows += '<div class="r"><span><i style="background:' + resolveColor(s.color) + '"></i>' + esc(s.name) + '</span><b>' + full(s.data[i] || 0) + '</b></div>';
        });
        tip.innerHTML = '<div class="t">' + esc(o.dates === false ? lab : shortDate(lab)) + '</div>' + rows;
        tip.classList.add('show');
        var hr = host.getBoundingClientRect();
        placeTip(host, tip, ev.clientX - hr.left, ev.clientY - hr.top);
      };
      hit.addEventListener('pointermove', show);
      hit.addEventListener('pointerdown', show);
      hit.addEventListener('pointerleave', function () { tip.classList.remove('show'); });
    });
  }

  /* ---------------------------------------------------------------- donut */
  function drawDonut(host, o) {
    var items = (o.items || []).filter(function (i) { return i.value > 0; });
    var total = items.reduce(function (a, i) { return a + i.value; }, 0);
    if (!total) return emptyState(host, o.empty);
    host.innerHTML = '';
    var size = Math.min(host.clientWidth || 200, o.size || 190);
    var R = size / 2 - 12, C = 2 * Math.PI * R;
    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:.9rem';
    host.appendChild(wrap);
    var box = document.createElement('div');
    box.style.cssText = 'position:relative;width:' + size + 'px;height:' + size + 'px';
    wrap.appendChild(box);
    var root = svg('svg', { viewBox: '0 0 ' + size + ' ' + size, width: size, height: size, role: 'img' }, box);
    root.style.overflow = 'visible';
    svg('circle', { cx: size / 2, cy: size / 2, r: R, fill: 'none', stroke: cssVar('--surface-2'), 'stroke-width': 18 }, root);
    var center = document.createElement('div');
    center.style.cssText = 'position:absolute;inset:0;display:grid;place-items:center;text-align:center;pointer-events:none';
    box.appendChild(center);
    function setCenter(big, small) {
      center.innerHTML = '<div><div style="font-size:1.35rem;font-weight:750;letter-spacing:-.02em;color:var(--ink)">' + esc(big) +
        '</div><div style="font-size:.7rem;color:var(--muted);max-width:7rem">' + esc(small) + '</div></div>';
    }
    setCenter(compact(total), o.centerLabel || 'total');
    var offset = 0, gap = items.length > 1 ? 2 : 0;
    items.forEach(function (it) {
      var len = (it.value / total) * C;
      var seg = svg('circle', {
        cx: size / 2, cy: size / 2, r: R, fill: 'none', stroke: resolveColor(it.color), 'stroke-width': 18,
        'stroke-dasharray': Math.max(len - gap, 0.5) + ' ' + C, 'stroke-dashoffset': -offset,
        transform: 'rotate(-90 ' + size / 2 + ' ' + size / 2 + ')', style: 'cursor:pointer;transition:opacity .15s'
      }, root);
      offset += len;
      var on = function () {
        setCenter(compact(it.value), it.label + ' · ' + Math.round((it.value / total) * 100) + '%');
        root.querySelectorAll('circle[stroke-dasharray]').forEach(function (c) { c.style.opacity = c === seg ? 1 : .35; });
      };
      var off = function () {
        setCenter(compact(total), o.centerLabel || 'total');
        root.querySelectorAll('circle[stroke-dasharray]').forEach(function (c) { c.style.opacity = 1; });
      };
      seg.addEventListener('pointerenter', on); seg.addEventListener('pointerleave', off); seg.addEventListener('pointerdown', on);
    });
    var lg = document.createElement('div');
    lg.className = 'legend';
    lg.style.justifyContent = 'center';
    lg.innerHTML = items.map(function (it) {
      return '<span><i style="background:' + resolveColor(it.color) + '"></i>' + esc(it.label) + '</span>';
    }).join('');
    wrap.appendChild(lg);
  }

  /* ------------------------------------------------------------- sparkline */
  function drawSpark(host, o) {
    var data = o.data || [];
    host.innerHTML = '';
    if (data.length < 2 || !data.some(function (v) { return v > 0; })) return;
    var W = Math.max(host.clientWidth, 60), H = o.height || 34;
    var max = Math.max.apply(null, data) || 1;
    var pts = data.map(function (v, i) { return [(i * (W - 2)) / (data.length - 1) + 1, H - 3 - (v / max) * (H - 8)]; });
    var color = resolveColor(o.color || 'var(--accent)');
    var root = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, height: H, 'aria-hidden': 'true' }, host);
    var id = 'psg' + (++uid);
    var defs = svg('defs', null, root);
    var lg = svg('linearGradient', { id: id, x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
    svg('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': .3 }, lg);
    svg('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': 0 }, lg);
    var d = smoothPath(pts);
    svg('path', { d: d + 'L' + pts[pts.length - 1][0] + ',' + H + 'L' + pts[0][0] + ',' + H + 'Z', fill: 'url(#' + id + ')' }, root);
    svg('path', { d: d, fill: 'none', stroke: color, 'stroke-width': 1.75, 'stroke-linecap': 'round' }, root);
  }

  var kinds = { line: drawLine, bar: drawBars, donut: drawDonut, spark: drawSpark };

  function render(host, kind, opts) {
    try { kinds[kind](host, opts); } catch (e) { emptyState(host, 'Chart could not be drawn'); if (window.console) console.error(e); }
  }

  var ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(function (entries) {
    entries.forEach(function (en) {
      registry.forEach(function (r) {
        if (r.host === en.target) {
          var w = Math.round(en.contentRect.width);
          if (w && w !== r.w) { r.w = w; render(r.host, r.kind, r.opts); }
        }
      });
    });
  }) : null;

  function mount(host, kind, opts) {
    if (typeof host === 'string') host = document.querySelector(host);
    if (!host) return null;
    var existing = registry.filter(function (r) { return r.host === host; })[0];
    if (existing) { existing.kind = kind; existing.opts = opts; existing.w = host.clientWidth; }
    else {
      registry.push({ host: host, kind: kind, opts: opts, w: host.clientWidth });
      if (ro) ro.observe(host);
    }
    render(host, kind, opts);
    return host;
  }

  /* theme switches change CSS variables, so redraw everything */
  document.addEventListener('ps-theme', function () {
    registry.forEach(function (r) { render(r.host, r.kind, r.opts); });
  });

  window.PSCharts = {
    line: function (h, o) { return mount(h, 'line', o); },
    bar: function (h, o) { return mount(h, 'bar', o); },
    donut: function (h, o) { return mount(h, 'donut', o); },
    spark: function (h, o) { return mount(h, 'spark', o); },
    compact: compact,
    full: full,
    esc: esc
  };
})();
