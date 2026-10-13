/* Post Studio — shared UI helpers: theme, mobile drawer, formatting, analytics fetch. */
(function () {
  'use strict';
  var root = document.documentElement;

  /* ------------------------------------------------------------------ theme */
  function applyTheme(t) {
    root.setAttribute('data-theme', t);
    root.classList.toggle('dark', t === 'dark');
    var m = document.querySelector('meta[name="theme-color"]');
    if (m) m.setAttribute('content', t === 'dark' ? '#0a0f1f' : '#f3f5fb');
    document.dispatchEvent(new CustomEvent('ps-theme', { detail: t }));
  }
  function currentTheme() { return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }

  document.addEventListener('click', function (e) {
    var tgl = e.target.closest('[data-theme-toggle]');
    if (tgl) {
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      try { localStorage.setItem('ps-theme', next); } catch (err) {}
      applyTheme(next);
      return;
    }
    if (e.target.closest('[data-drawer-open]')) { document.body.classList.add('drawer-open'); return; }
    if (e.target.closest('[data-drawer-close]') || e.target.closest('.app-sidebar a')) {
      document.body.classList.remove('drawer-open');
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.body.classList.remove('drawer-open');
  });
  /* follow the OS setting until the user picks a theme explicitly */
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: light)');
    var onChange = function (ev) {
      var stored = null;
      try { stored = localStorage.getItem('ps-theme'); } catch (err) {}
      if (!stored) applyTheme(ev.matches ? 'light' : 'dark');
    };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
  }

  /* ---------------------------------------------------------------- helpers */
  var PS = window.PS = window.PS || {};
  PS.platforms = window.PS_PLATFORMS || {};

  PS.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
  };
  PS.fmt = function (n) { return (Number(n) || 0).toLocaleString(); };
  PS.compact = function (n) { return window.PSCharts ? window.PSCharts.compact(n) : PS.fmt(n); };
  PS.pct = function (n) { return (Number(n) || 0).toFixed(1).replace(/\.0$/, '') + '%'; };
  PS.timeAgo = function (ts) {
    var mins = Math.max(0, Math.round((Date.now() / 1000 - Number(ts)) / 60));
    if (mins < 1) return 'just now';
    if (mins < 60) return mins + ' min ago';
    var h = Math.round(mins / 60);
    return h < 24 ? h + ' h ago' : Math.round(h / 24) + ' d ago';
  };
  PS.platform = function (key) {
    var p = PS.platforms[key];
    if (p) return p;
    key = String(key || 'other');
    return { name: key.charAt(0).toUpperCase() + key.slice(1), short: key.slice(0, 2).toUpperCase(), color: '#64748b' };
  };
  /* Brand colours that vanish against the current theme (e.g. TikTok black on dark) get a neutral stand-in. */
  PS.safeColor = function (hex) {
    var m = /^#([0-9a-f]{6})$/i.exec(hex || '');
    if (!m) return hex || '#64748b';
    var n = parseInt(m[1], 16), lum = (0.2126 * (n >> 16) + 0.7152 * ((n >> 8) & 255) + 0.0722 * (n & 255)) / 255;
    var dark = currentTheme() === 'dark';
    if (dark && lum < 0.2) return '#94a3b8';
    if (!dark && lum > 0.85) return '#64748b';
    return hex;
  };
  PS.platBadge = function (key) {
    var p = PS.platform(key);
    return '<span class="inline-flex items-center gap-2"><span class="plat-dot" style="background:' + PS.esc(p.color) + '">' +
      PS.esc(p.short) + '</span><span>' + PS.esc(p.name) + '</span></span>';
  };
  PS.serviceTag = function (s) {
    var map = { zernio: ['ZN', 'Zernio'], bulkpublish: ['BP', 'BulkPublish'], buffer: ['BF', 'Buffer'] };
    var m = map[s] || [String(s).slice(0, 2).toUpperCase(), s];
    return '<span class="pill pill-accent" style="padding:.1rem .45rem;font-size:.62rem" title="' + PS.esc(m[1]) + '">' + m[0] + '</span>';
  };
  PS.statusPill = function (st) {
    st = String(st || '').toLowerCase();
    var cls = st === 'published' || st === 'sent' ? 'pill-ok'
      : st === 'failed' ? 'pill-bad'
      : st === 'partial' ? 'pill-warn'
      : st === 'scheduled' || st === 'publishing' || st === 'processing' || st === 'pending' ? 'pill-info' : '';
    return '<span class="pill ' + cls + '"><span class="dot"></span>' + PS.esc(st ? st.charAt(0).toUpperCase() + st.slice(1) : '—') + '</span>';
  };
  PS.engRate = function (m) {
    var v = Number(m.views) || 0;
    if (v <= 0) return 0;
    return ((Number(m.likes) || 0) + (Number(m.comments) || 0) + (Number(m.shares) || 0) + (Number(m.saves) || 0)) / v * 100;
  };

  /* Fetch the aggregated analytics (server handles caching / throttling). */
  PS.fetchAnalytics = async function (force) {
    var res = await fetch('ajax/refresh_analytics.php' + (force ? '?force=1' : ''), { credentials: 'same-origin' });
    var data;
    try { data = await res.json(); }
    catch (e) { throw new Error(res.status === 401 ? 'Session expired — please log in again.' : 'Unexpected server response. Please try again.'); }
    if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to load analytics');
    return data;
  };

  PS.metricMeta = {
    views:    { label: 'Views',    color: 'var(--c2)' },
    likes:    { label: 'Likes',    color: 'var(--c5)' },
    comments: { label: 'Comments', color: 'var(--c4)' },
    shares:   { label: 'Shares',   color: 'var(--c3)' }
  };
})();
