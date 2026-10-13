<?php
$page_title = 'Analytics';
$active = 'analytics';
require_once __DIR__ . '/includes/header.php';
$trackedViews = (int)get_engagement_views();
?>

<!-- Page heading + controls -->
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
  <div class="min-w-0">
    <h2 class="text-xl font-bold tracking-tight text-ink flex items-center flex-wrap gap-2">
      Performance overview
      <span id="an-fresh" class="pill hidden"></span>
    </h2>
    <p class="text-sm text-muted mt-1">Views, likes, comments and shares across Zernio, BulkPublish and Buffer.</p>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <div class="seg" id="an-range" role="group" aria-label="Chart range">
      <button type="button" data-range="7">7d</button>
      <button type="button" data-range="14">14d</button>
      <button type="button" data-range="30" class="on">30d</button>
    </div>
    <button id="an-refresh" type="button" class="btn btn-primary btn-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
      <span>Refresh</span>
    </button>
  </div>
</div>

<div id="an-notices" class="space-y-3"></div>

<!-- Loading skeleton -->
<div id="an-loading" class="space-y-4">
  <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
    <?php for ($i = 0; $i < 6; $i++): ?><div class="card skeleton" style="height:108px"></div><?php endfor; ?>
  </div>
  <div class="card skeleton" style="height:320px"></div>
</div>

<div id="an-error" class="hidden card card-pad text-sm" style="color:var(--bad);border-color:color-mix(in srgb,var(--bad) 35%,transparent)"></div>

<div id="an-empty" class="hidden card card-pad text-center py-14">
  <div class="text-4xl mb-3">📊</div>
  <h3 class="text-lg font-semibold text-ink">No posts to analyse yet</h3>
  <p class="text-sm text-muted mt-1 max-w-md mx-auto">Publish a post from the composer and its numbers will appear here.</p>
</div>

<div id="an-content" class="hidden space-y-5">

  <!-- KPIs -->
  <div id="an-kpis" class="grid grid-cols-2 lg:grid-cols-6 gap-3"></div>

  <!-- Main trend + platform donut -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
    <section class="card xl:col-span-2">
      <div class="card-head">
        <div>
          <div class="card-title">Engagement by publish date</div>
          <div class="card-sub">Totals for posts published on each day</div>
        </div>
        <div class="seg" id="an-metric" role="group" aria-label="Metric">
          <button type="button" data-metric="views" class="on">Views</button>
          <button type="button" data-metric="likes">Likes</button>
          <button type="button" data-metric="comments">Comments</button>
          <button type="button" data-metric="shares">Shares</button>
          <button type="button" data-metric="all">All</button>
        </div>
      </div>
      <div class="p-4 sm:p-5">
        <div id="an-chart-main" class="chart"></div>
        <div id="an-legend-main" class="legend mt-3"></div>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <div>
          <div class="card-title">By platform</div>
          <div class="card-sub" id="an-donut-sub">Share of views</div>
        </div>
      </div>
      <div class="p-4 sm:p-5"><div id="an-chart-donut" class="chart"></div></div>
    </section>
  </div>

  <!-- Growth + activity -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <section class="card">
      <div class="card-head">
        <div>
          <div class="card-title">Growth over time</div>
          <div class="card-sub">Running totals, recorded each day you open analytics</div>
        </div>
      </div>
      <div class="p-4 sm:p-5">
        <div id="an-chart-history" class="chart"></div>
        <div id="an-history-note" class="text-xs text-muted mt-2 hidden"></div>
      </div>
    </section>
    <section class="card">
      <div class="card-head">
        <div>
          <div class="card-title">Posting activity</div>
          <div class="card-sub">Published posts per day</div>
        </div>
      </div>
      <div class="p-4 sm:p-5"><div id="an-chart-activity" class="chart"></div></div>
    </section>
  </div>

  <!-- Platform breakdown + top posts -->
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    <section class="card">
      <div class="card-head"><div class="card-title">Platform breakdown</div><div class="card-sub" id="an-plat-sub"></div></div>
      <div id="an-platforms" class="p-2 sm:p-3"></div>
    </section>
    <section class="card">
      <div class="card-head"><div class="card-title">Top posts</div><div class="card-sub">Ranked by views + interactions</div></div>
      <div id="an-top"></div>
    </section>
  </div>

  <!-- Posts table -->
  <section class="card overflow-hidden">
    <div class="card-head">
      <div>
        <div class="card-title">All posts</div>
        <div class="card-sub" id="an-table-sub"></div>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <input id="an-search" type="search" class="input" style="width:12rem;padding:.4rem .7rem" placeholder="Search posts…" aria-label="Search posts">
        <select id="an-service" class="select" style="width:auto;padding:.4rem 2rem .4rem .7rem" aria-label="Filter by service">
          <option value="all">All services</option>
          <option value="zernio">Zernio</option>
          <option value="bulkpublish">BulkPublish</option>
          <option value="buffer">Buffer</option>
        </select>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="table rtable">
        <thead>
          <tr>
            <th>Post</th>
            <th>Status</th>
            <th class="sortable num" data-sort="views">Views</th>
            <th class="sortable num" data-sort="likes">Likes</th>
            <th class="sortable num" data-sort="comments">Comments</th>
            <th class="sortable num" data-sort="shares">Shares</th>
            <th class="sortable num" data-sort="rate">Eng.</th>
          </tr>
        </thead>
        <tbody id="an-tbody"></tbody>
      </table>
    </div>
    <div class="p-3 text-center" id="an-more-wrap" style="border-top:1px solid var(--border)">
      <button id="an-more" type="button" class="btn btn-ghost btn-sm">Show more</button>
    </div>
  </section>
</div>

<!-- Tracking check -->
<section class="card">
  <div class="card-head">
    <div>
      <div class="card-title">Tracking check</div>
      <div class="card-sub">Numbers look wrong or stay at zero? See exactly what each service returns for a post.<?php if ($trackedViews > 0): ?> · <?= number_format($trackedViews) ?> post detail view<?= $trackedViews === 1 ? '' : 's' ?> opened in this app<?php endif; ?></div>
    </div>
    <button id="an-debug-btn" type="button" class="btn btn-ghost btn-sm">Run check</button>
  </div>
  <div id="an-debug" class="hidden p-4 sm:p-5 space-y-4"></div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var $ = function (id) { return document.getElementById(id); };
  var esc = PS.esc, fmt = PS.fmt;
  var DATA = null, range = 30, metric = 'views', sortKey = 'views', sortDir = -1, service = 'all', query = '', shown = 15;

  var KPI = [
    { key: 'views',    label: 'Views',    color: 'var(--c2)' },
    { key: 'likes',    label: 'Likes',    color: 'var(--c5)' },
    { key: 'comments', label: 'Comments', color: 'var(--c4)' },
    { key: 'shares',   label: 'Shares',   color: 'var(--c3)' }
  ];

  function show(which) {
    ['an-loading', 'an-error', 'an-empty', 'an-content'].forEach(function (id) {
      $(id).classList.toggle('hidden', id !== which);
    });
  }

  function notices(d) {
    var out = [];
    if (d.throttled) out.push(['info', 'Refreshed a moment ago — showing recent data to protect your API quota.']);
    (d.warnings || []).forEach(function (w) { out.push(['warn', '<strong>Could not refresh:</strong> ' + esc(w)]); });
    var cov = d.coverage || {};
    Object.keys(cov).forEach(function (svc) {
      var c = cov[svc];
      if (c.posts > 0 && c.with_metrics === 0) {
        out.push(['warn', '<strong>' + esc(svc) + '</strong> returned no engagement numbers for its ' + c.posts +
          ' post' + (c.posts === 1 ? '' : 's') + '. That can be normal for brand-new posts, otherwise run the <a href="#an-debug-btn" class="underline">Tracking check</a> below.']);
      }
      if (c.analytics_endpoint && c.analytics_endpoint !== 'ok') {
        out.push(['info', 'Zernio analytics endpoint: ' + esc(c.analytics_endpoint)]);
      }
    });
    $('an-notices').innerHTML = out.map(function (n) {
      var color = n[0] === 'warn' ? 'var(--warn)' : 'var(--info)';
      var bg = n[0] === 'warn' ? 'var(--warn-soft)' : 'var(--info-soft)';
      return '<div class="px-4 py-3 rounded-xl text-sm" style="color:' + color + ';background:' + bg + '">' + n[1] + '</div>';
    }).join('');
  }

  function slice() { return (DATA.timeline || []).slice(-range); }

  function renderKpis() {
    var s = DATA.stats, tl = slice();
    var tracked = s.tracked_posts || 0;
    var cards = KPI.map(function (k) {
      var avg = tracked ? PS.compact(Math.round(s[k.key] / tracked)) + ' avg / post' : 'No data yet';
      return '<div class="card kpi"><div class="kpi-label"><i style="background:' + k.color + '"></i>' + k.label + '</div>' +
        '<div class="kpi-value">' + PS.compact(s[k.key]) + '</div><div class="kpi-foot">' + avg + '</div>' +
        '<div class="kpi-spark" data-spark="' + k.key + '"></div></div>';
    });
    cards.push('<div class="card kpi"><div class="kpi-label"><i style="background:var(--c1)"></i>Engagement rate</div>' +
      '<div class="kpi-value">' + PS.pct(s.engagement_rate) + '</div><div class="kpi-foot">' + fmt(s.engagements) + ' interactions</div></div>');
    cards.push('<div class="card kpi"><div class="kpi-label"><i style="background:var(--c6)"></i>Posts</div>' +
      '<div class="kpi-value">' + fmt(s.total) + '</div><div class="kpi-foot">' + fmt(s.published) + ' published' +
      (s.failed ? ' · <span style="color:var(--bad)">' + fmt(s.failed) + ' failed</span>' : '') + (s.scheduled ? ' · ' + fmt(s.scheduled) + ' queued' : '') + '</div></div>');
    $('an-kpis').innerHTML = cards.join('');
    KPI.forEach(function (k) {
      PSCharts.spark($('an-kpis').querySelector('[data-spark="' + k.key + '"]'), { data: tl.map(function (r) { return r[k.key]; }), color: k.color });
    });
  }

  function renderMain() {
    var tl = slice(), labels = tl.map(function (r) { return r.date; });
    var series, legend = '';
    if (metric === 'all') {
      series = ['likes', 'comments', 'shares'].map(function (k) {
        return { name: PS.metricMeta[k].label, color: PS.metricMeta[k].color, data: tl.map(function (r) { return r[k]; }) };
      });
      legend = series.map(function (s) { return '<span><i style="background:' + s.color + '"></i>' + s.name + '</span>'; }).join('');
    } else {
      series = [{ name: PS.metricMeta[metric].label, color: PS.metricMeta[metric].color, data: tl.map(function (r) { return r[metric]; }) }];
    }
    $('an-legend-main').innerHTML = legend;
    PSCharts.line('#an-chart-main', { labels: labels, series: series, areaAll: metric === 'all',
      empty: 'No ' + (metric === 'all' ? 'engagement' : metric) + ' recorded for posts published in this period.' });
  }

  function renderDonut() {
    var bp = DATA.by_platform || {}, colors = ['var(--c1)', 'var(--c2)', 'var(--c3)', 'var(--c4)', 'var(--c5)', 'var(--c6)'];
    var keys = Object.keys(bp);
    var useViews = keys.some(function (k) { return bp[k].views > 0; });
    var items = keys.map(function (k, i) {
      var p = PS.platform(k);
      return { label: p.name, value: useViews ? bp[k].views : bp[k].posts, color: PS.safeColor(p.color) || colors[i % colors.length] };
    }).sort(function (a, b) { return b.value - a.value; });
    $('an-donut-sub').textContent = useViews ? 'Share of views' : 'Share of posts (no view counts yet)';
    PSCharts.donut('#an-chart-donut', { items: items, centerLabel: useViews ? 'views' : 'posts', empty: 'Connect a channel and publish to see this.' });
  }

  function renderHistory() {
    var h = DATA.history || [];
    var note = $('an-history-note');
    if (h.length < 2) {
      PSCharts.line('#an-chart-history', { labels: [], series: [], empty: 'History starts building today. Check back tomorrow to see growth.' });
      note.classList.add('hidden');
      return;
    }
    PSCharts.line('#an-chart-history', {
      labels: h.map(function (r) { return r.date; }),
      series: ['views', 'likes', 'comments', 'shares'].map(function (k) {
        return { name: PS.metricMeta[k].label, color: PS.metricMeta[k].color, data: h.map(function (r) { return r[k]; }) };
      }), area: false, empty: 'No tracked numbers yet.'
    });
    $('an-history-note').innerHTML = '<span class="legend"><span><i style="background:var(--c2)"></i>Views</span><span><i style="background:var(--c5)"></i>Likes</span><span><i style="background:var(--c4)"></i>Comments</span><span><i style="background:var(--c3)"></i>Shares</span></span>';
    note.classList.remove('hidden');
  }

  function renderActivity() {
    var tl = slice();
    PSCharts.bar('#an-chart-activity', { labels: tl.map(function (r) { return r.date; }),
      series: [{ name: 'Posts', color: 'var(--c1)', data: tl.map(function (r) { return r.posts; }) }], empty: 'No posts published in this period.' });
  }

  function renderPlatforms() {
    var bp = DATA.by_platform || {}, keys = Object.keys(bp).sort(function (a, b) {
      return (bp[b].views + bp[b].likes) - (bp[a].views + bp[a].likes) || bp[b].posts - bp[a].posts;
    });
    if (!keys.length) { $('an-platforms').innerHTML = '<div class="chart-empty">No platform data yet.</div>'; return; }
    var maxViews = Math.max.apply(null, keys.map(function (k) { return bp[k].views; })) || 1;
    $('an-plat-sub').textContent = keys.length + ' platform' + (keys.length === 1 ? '' : 's');
    $('an-platforms').innerHTML = keys.map(function (k) {
      var p = bp[k], pl = PS.platform(k), rate = PS.engRate(p);
      return '<div class="px-3 py-3 rounded-xl" style="border-bottom:1px solid var(--border)"><div class="flex items-center justify-between gap-3">' +
        PS.platBadge(k) + '<span class="text-xs text-muted">' + fmt(p.posts) + ' post' + (p.posts === 1 ? '' : 's') + '</span></div>' +
        '<div class="meter mt-2"><span style="width:' + Math.max(2, p.views / maxViews * 100) + '%;background:' + esc(pl.color) + '"></span></div>' +
        '<div class="grid grid-cols-4 gap-2 mt-2 text-xs tabular">' +
        '<div><span class="text-muted">Views</span><div class="font-semibold text-ink">' + PS.compact(p.views) + '</div></div>' +
        '<div><span class="text-muted">Likes</span><div class="font-semibold text-ink">' + PS.compact(p.likes) + '</div></div>' +
        '<div><span class="text-muted">Comments</span><div class="font-semibold text-ink">' + PS.compact(p.comments) + '</div></div>' +
        '<div><span class="text-muted">Shares</span><div class="font-semibold text-ink">' + PS.compact(p.shares) + '</div></div></div>' +
        (p.views ? '<div class="text-[11px] text-muted mt-1">Engagement rate ' + PS.pct(rate) + '</div>' : '') + '</div>';
    }).join('');
  }

  function renderTop() {
    var top = DATA.top_posts || [];
    if (!top.length) { $('an-top').innerHTML = '<div class="chart-empty">Top posts appear once a service reports numbers.</div>'; return; }
    $('an-top').innerHTML = top.map(function (p, i) {
      var m = p.metrics;
      var title = esc(p.content || 'No caption');
      var link = p.url ? '<a href="' + esc(p.url) + '" target="_blank" rel="noopener" class="hover:underline">' + title + '</a>' : title;
      return '<div class="px-4 py-3 flex items-start gap-3" style="border-bottom:1px solid var(--border)">' +
        '<div class="w-6 h-6 shrink-0 rounded-lg grid place-items-center text-[11px] font-bold" style="background:var(--accent-soft);color:var(--accent)">' + (i + 1) + '</div>' +
        '<div class="min-w-0 flex-1"><div class="text-sm font-medium text-ink truncate">' + link + '</div>' +
        '<div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-muted tabular">' + PS.serviceTag(p.service) +
        '<span>👁 ' + PS.compact(m.views) + '</span><span>♥ ' + PS.compact(m.likes) + '</span><span>💬 ' + PS.compact(m.comments) + '</span><span>↗ ' + PS.compact(m.shares) + '</span></div></div></div>';
    }).join('');
  }

  function renderTable() {
    var rows = (DATA.posts || []).filter(function (p) {
      if (service !== 'all' && p.service !== service) return false;
      if (query && (p.content || '').toLowerCase().indexOf(query) === -1) return false;
      return true;
    }).map(function (p) { return { p: p, rate: PS.engRate(p.metrics) }; });
    rows.sort(function (a, b) {
      var av = sortKey === 'rate' ? a.rate : a.p.metrics[sortKey], bv = sortKey === 'rate' ? b.rate : b.p.metrics[sortKey];
      return (av - bv) * sortDir || (b.p._sortTime - a.p._sortTime);
    });
    $('an-table-sub').textContent = fmt(rows.length) + ' post' + (rows.length === 1 ? '' : 's');
    var visible = rows.slice(0, shown);
    $('an-tbody').innerHTML = visible.map(function (r) {
      var p = r.p, m = p.metrics;
      var plats = (p.platforms || []).map(function (pl) {
        return '<span class="pill" style="padding:.05rem .45rem;font-size:.62rem;text-transform:uppercase">' + esc(pl.platform) + '</span>';
      }).join(' ');
      var dash = p.has_metrics ? '' : ' style="color:var(--faint)"';
      var cell = function (label, v) { return '<td class="num" data-label="' + label + '"' + dash + '>' + (p.has_metrics ? fmt(v) : '—') + '</td>'; };
      return '<tr><td class="span"><div class="flex items-center gap-2">' + PS.serviceTag(p.service) +
        '<div class="text-sm font-medium text-ink truncate" style="max-width:26rem">' + (esc(p.content) || '<span class="text-faint italic">No caption</span>') + '</div></div>' +
        '<div class="flex flex-wrap gap-1 mt-1">' + plats + '</div></td>' +
        '<td class="span">' + PS.statusPill(p.status) + '</td>' +
        cell('Views', m.views) + cell('Likes', m.likes) + cell('Comments', m.comments) + cell('Shares', m.shares) +
        '<td class="num" data-label="Eng."' + dash + '>' + (p.has_metrics && m.views ? PS.pct(r.rate) : '—') + '</td></tr>';
    }).join('') || '<tr><td colspan="7" class="text-center text-muted py-8">No posts match.</td></tr>';
    $('an-more-wrap').classList.toggle('hidden', rows.length <= shown);
  }

  function renderAll() {
    renderKpis(); renderMain(); renderDonut(); renderHistory(); renderActivity(); renderPlatforms(); renderTop(); renderTable();
  }

  function freshness(d) {
    var el = $('an-fresh');
    if (!d.cached_at) { el.classList.add('hidden'); return; }
    el.textContent = 'Updated ' + PS.timeAgo(d.cached_at);
    el.classList.remove('hidden');
  }

  async function load(force) {
    var btn = $('an-refresh');
    btn.disabled = true; btn.querySelector('span').textContent = force ? 'Refreshing…' : 'Loading…';
    if (!DATA) show('an-loading');
    try {
      DATA = await PS.fetchAnalytics(force);
      freshness(DATA); notices(DATA);
      if (!DATA.posts || !DATA.posts.length) { show('an-empty'); return; }
      show('an-content');
      renderAll();
    } catch (e) {
      $('an-error').innerHTML = '<strong>Error:</strong> ' + esc(e.message);
      if (!DATA) show('an-error'); else $('an-error').classList.remove('hidden');
    } finally {
      btn.disabled = false; btn.querySelector('span').textContent = 'Refresh';
    }
  }

  document.addEventListener('ps-theme', function () { if (DATA) { renderDonut(); renderKpis(); } });

  /* controls */
  $('an-refresh').addEventListener('click', function () { load(true); });
  $('an-range').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b || !DATA) return;
    range = Number(b.dataset.range);
    this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); });
    renderKpis(); renderMain(); renderActivity();
  });
  $('an-metric').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b || !DATA) return;
    metric = b.dataset.metric;
    this.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); });
    renderMain();
  });
  $('an-service').addEventListener('change', function () { service = this.value; shown = 15; if (DATA) renderTable(); });
  $('an-search').addEventListener('input', function () { query = this.value.trim().toLowerCase(); shown = 15; if (DATA) renderTable(); });
  $('an-more').addEventListener('click', function () { shown += 20; renderTable(); });
  document.querySelectorAll('th.sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var k = th.dataset.sort;
      if (sortKey === k) sortDir = -sortDir; else { sortKey = k; sortDir = -1; }
      if (DATA) renderTable();
    });
  });

  /* tracking check */
  $('an-debug-btn').addEventListener('click', async function () {
    var box = $('an-debug'), btn = this;
    box.classList.remove('hidden'); box.innerHTML = '<div class="text-sm text-muted">Asking each service for a sample post…</div>';
    btn.disabled = true;
    try {
      var res = await fetch('ajax/analytics_debug.php', { credentials: 'same-origin' });
      var j = await res.json();
      if (!j.ok) throw new Error(j.error || 'Check failed');
      if (!j.report.length) { box.innerHTML = '<div class="text-sm text-muted">No API keys configured.</div>'; return; }
      box.innerHTML = j.report.map(function (r) {
        var parts = '<div class="font-semibold text-ink mb-2">' + PS.serviceTag(r.service) + ' ' + esc(r.service) + '</div>';
        Object.keys(r).forEach(function (k) {
          if (k === 'service') return;
          var v = r[k], isErr = /error/.test(k);
          var extracted = v && v.extracted ? '<div class="text-xs mt-1 mb-2 ' + (v.extracted.found ? '' : 'text-muted') + '" style="' + (v.extracted.found ? 'color:var(--ok)' : '') + '">' +
            (v.extracted.found ? 'Counters read → views ' + fmt(v.extracted.views) + ', likes ' + fmt(v.extracted.likes) + ', comments ' + fmt(v.extracted.comments) + ', shares ' + fmt(v.extracted.shares)
              : 'No engagement counters found in this response.') + '</div>' : '';
          parts += '<div class="mb-3"><div class="label">' + esc(k.replace(/_/g, ' ')) + '</div>' + extracted +
            '<pre class="text-xs rounded-lg p-3 overflow-auto" style="max-height:16rem;background:var(--surface-2);border:1px solid var(--border);color:' + (isErr ? 'var(--bad)' : 'var(--ink-2)') + '">' +
            esc(typeof v === 'string' ? v : JSON.stringify(v.sample !== undefined ? { top_level_keys: v.top_level_keys, sample: v.sample } : v, null, 2)) + '</pre></div>';
        });
        return '<div>' + parts + '</div>';
      }).join('<hr style="border-color:var(--border)">');
    } catch (e) {
      box.innerHTML = '<div class="text-sm" style="color:var(--bad)">' + esc(e.message) + '</div>';
    } finally { btn.disabled = false; }
  });

  load(false);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
