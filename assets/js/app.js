/* Post Studio - front-end logic */
(function () {
  'use strict';

  /* ---------------- utilities ---------------- */

  function toast(msg, type) {
    let wrap = document.getElementById('toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'toast-wrap';
      document.body.appendChild(wrap);
    }
    const el = document.createElement('div');
    el.className = 'toast toast-' + (type || 'info');
    el.textContent = msg;
    wrap.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transition = 'opacity .3s';
      setTimeout(function () { el.remove(); }, 320);
    }, 3800);
  }

  function csrf() {
    const m = document.querySelector('meta[name="csrf"]');
    return m ? m.content : '';
  }

  async function api(url, options) {
    options = options || {};
    const opts = {
      method: options.method || 'POST',
      headers: Object.assign({ 'X-CSRF-Token': csrf() }, options.headers || {}),
    };
    if (options.body instanceof FormData) {
      opts.body = options.body;
    } else if (options.body && typeof options.body !== 'string') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(options.body);
    }
    const res = await fetch(url, opts);
    let data = null;
    try { data = await res.json(); } catch (e) { /* ignore */ }
    if (!res.ok) {
      throw new Error((data && data.error) || 'Request failed (HTTP ' + res.status + ')');
    }
    return data;
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function fmtDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return esc(iso);
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
  }

  /* ---------------- platform option definitions ---------------- */

  const PLATFORM_OPTS = {
    youtube: {
      note: 'Shorts are auto-detected by YouTube (\u22643 min + vertical 9:16) \u2014 no flag needed. Upload a vertical MP4 under 3 min for a Short.',
      fields: [
        { k: 'title', type: 'text', label: 'Video title', ph: 'Required for YouTube', req: true },
        { k: 'visibility', type: 'select', label: 'Visibility', opts: [['public', 'Public'], ['unlisted', 'Unlisted'], ['private', 'Private']], def: 'public' },
        { k: 'categoryId', type: 'text', label: 'Category ID', ph: 'e.g. 22 (People & Blogs), 27 (Education)' },
        { k: 'madeForKids', type: 'toggle', label: 'Made for kids (COPPA)', def: false },
        { k: 'firstComment', type: 'text', label: 'First comment', ph: 'Optional' },
      ],
    },
    instagram: {
      note: 'Single videos publish as Reels automatically. Multiple images create a carousel. Stories expire after 24h.',
      fields: [
        { k: 'contentType', type: 'select', label: 'Post format', opts: [['auto', 'Feed / Carousel / Reel (auto)'], ['reel', 'Reel (short vertical video)'], ['story', 'Story']], def: 'auto' },
        { k: 'firstComment', type: 'text', label: 'First comment (feed/carousel)', ph: 'Useful for links (captions have no clickable links)' },
      ],
    },
    facebook: {
      fields: [
        { k: 'contentType', type: 'select', label: 'Post format', opts: [['feed', 'Feed'], ['reel', 'Reel (short vertical video)'], ['story', 'Story']], def: 'feed' },
        { k: 'title', type: 'text', label: 'Reel title', ph: 'Only used for Reels', dep: ['reel'] },
        { k: 'firstComment', type: 'text', label: 'First comment', ph: 'Optional' },
      ],
    },
    tiktok: {
      note: 'TikTok legally requires content_preview_confirmed and express_consent_given = true \u2014 we send them automatically.',
      fields: [
        { k: 'privacy_level', type: 'select', label: 'Privacy', opts: [['PUBLIC_TO_EVERYONE', 'Public'], ['MUTUAL_FOLLOW_FRIENDS', 'Friends'], ['FOLLOWER_OF_CREATOR', 'Followers'], ['SELF_ONLY', 'Only me']], def: 'PUBLIC_TO_EVERYONE' },
        { k: 'allow_comment', type: 'toggle', label: 'Allow comments', def: true },
        { k: 'allow_duet', type: 'toggle', label: 'Allow duets', def: true },
        { k: 'allow_stitch', type: 'toggle', label: 'Allow stitches', def: true },
        { k: 'video_cover_image_url', type: 'text', label: 'Cover image URL', ph: 'JPG / PNG / WebP, max 20MB' },
      ],
    },
    linkedin: {
      note: 'LinkedIn suppresses posts with external links (reach drops 40\u201350%). Put links in the first comment.',
      fields: [
        { k: 'firstComment', type: 'text', label: 'First comment', ph: 'Recommended for links' },
      ],
    },
    twitter: { fields: [] },
    pinterest: { fields: [] },
    reddit: { fields: [] },
    bluesky: { fields: [] },
    threads: { fields: [] },
    telegram: { fields: [] },
    snapchat: { fields: [] },
    whatsapp: { fields: [] },
    discord: { fields: [] },
    googlebusiness: { fields: [] },
  };

  function platformName(p) {
    const m = {
      twitter: 'X (Twitter)', instagram: 'Instagram', facebook: 'Facebook', linkedin: 'LinkedIn',
      tiktok: 'TikTok', youtube: 'YouTube', pinterest: 'Pinterest', reddit: 'Reddit',
      bluesky: 'Bluesky', threads: 'Threads', googlebusiness: 'Google Business',
      telegram: 'Telegram', snapchat: 'Snapchat', whatsapp: 'WhatsApp', discord: 'Discord', slack: 'Slack',
    };
    return m[p] || p;
  }

  /* ---------------- composer ---------------- */

  const composer = {
    accounts: [],
    services: {},
    selected: new Set(),
    media: [],
    postType: 'caption',
    platformValues: {},
    schedule: { mode: 'now', scheduledFor: '', timezone: 'UTC' },

    init() {
      const f = document.getElementById('composer-form');
      if (!f) return;
      this.loadAccounts();
      this.bindTypeSelect();
      this.bindMedia();
      this.bindSchedule();
      this.bindSubmit(f);
    },

    async loadAccounts() {
      const grid = document.getElementById('accounts-grid');
      grid.innerHTML = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">'
        + '<div class="skeleton h-16"></div><div class="skeleton h-16"></div><div class="skeleton h-16"></div></div>';
      try {
        const data = await api('ajax/list_accounts.php', { method: 'GET' });
        this.accounts = data.accounts || [];
        this.services = data.services || {};
        if (!this.accounts.length) {
          grid.innerHTML = '<div class="p-6 text-center text-sm text-slate-400 rounded-xl border border-dashed border-slate-700">'
            + 'No connected social accounts found. Connect accounts in Zernio / BulkPublish, then refresh.</div>';
          return;
        }
        this.renderAccounts(grid);
      } catch (e) {
        grid.innerHTML = '<div class="p-6 text-center text-sm text-rose-300 rounded-xl border border-dashed border-rose-500/40">'
          + esc(e.message) + '</div>';
      }
    },

    renderAccounts(grid) {
      const byPlatform = {};
      this.accounts.forEach(function (a) {
        (byPlatform[a.platform] = byPlatform[a.platform] || []).push(a);
      });
      const order = Object.keys(byPlatform).sort();
      let html = '';
      const self = this;
      order.forEach(function (p) {
        const accts = byPlatform[p];
        html += '<div class="mb-4">';
        html += '<div class="flex items-center gap-2 mb-2"><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">' + esc(platformName(p)) + '</span>'
          + '<span class="text-[11px] text-slate-600">(' + accts.length + ')</span>'
          + '<button type="button" class="text-[11px] text-violet-400 hover:text-violet-300 ml-auto" data-sel-all="' + esc(p) + '">Select all</button></div>';
        html += '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">';
        accts.forEach(function (a) {
          const id = 'acc-' + a._id;
          const svc = a.service === 'bulkpublish'
            ? '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25 ml-auto shrink-0" title="BulkPublish">BP</span>'
            : '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-violet-500/15 text-violet-300 border border-violet-500/25 ml-auto shrink-0" title="Zernio">ZN</span>';
          html += '<label for="' + esc(id) + '" class="account-card option-card px-3 py-2.5 flex items-center gap-2.5" data-platform="' + esc(a.platform) + '">'
            + '<input type="checkbox" id="' + esc(id) + '" class="account-check h-4 w-4 rounded border-slate-600 text-violet-500 focus:ring-violet-500 focus:ring-offset-0 bg-slate-800" data-account="' + esc(a._id) + '">'
            + '<span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold flex-none" style="background:' + esc(a.color || '#64748b') + '">' + esc((a.short || a.platform.slice(0, 2)).toUpperCase()) + '</span>'
            + '<span class="min-w-0"><span class="block text-sm font-medium truncate">' + esc(a.displayName || a.username) + '</span>'
            + '<span class="block text-[11px] text-slate-500 truncate">' + esc(a.username || '') + '</span></span>'
            + svc
            + (a.tokenStatus === 'expired' ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-300">expired</span>' : '')
            + (a.isActive === false ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-300">offline</span>' : '')
            + '</label>';
        });
        html += '</div></div>';
      });
      grid.innerHTML = html;
      grid.querySelectorAll('.account-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
          const acc = self.accounts.find(function (a) { return a._id === cb.dataset.account; });
          if (!acc) return;
          if (cb.checked) {
            self.selected.add(acc._id);
            cb.closest('.account-card').classList.add('selected');
          } else {
            self.selected.delete(acc._id);
            cb.closest('.account-card').classList.remove('selected');
          }
          self.renderPlatformOptions();
        });
      });
      grid.querySelectorAll('[data-sel-all]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const p = btn.dataset.selAll;
          const want = !self.accounts.some(function (a) { return a.platform === p && self.selected.has(a._id); });
          self.accounts.forEach(function (a) {
            if (a.platform !== p) return;
            if (want) self.selected.add(a._id); else self.selected.delete(a._id);
          });
          grid.querySelectorAll('.account-card[data-platform="' + p + '"]').forEach(function (card) {
            const cb = card.querySelector('.account-check');
            cb.checked = want;
            card.classList.toggle('selected', want);
          });
          self.renderPlatformOptions();
        });
      });
    },

    bindTypeSelect() {
      const radios = document.querySelectorAll('input[name="post_type"]');
      const self = this;
      radios.forEach(function (r) {
        r.addEventListener('change', function () {
          document.querySelectorAll('.type-card').forEach(function (c) {
            c.classList.toggle('selected', c.querySelector('input').checked);
          });
          self.postType = r.value;
          document.getElementById('media-section').classList.toggle('hidden', self.postType === 'caption');
          document.getElementById('caption-label').textContent =
            self.postType === 'caption' ? 'Caption (required)' : 'Caption (optional)';
        });
      });
    },

    bindMedia() {
      const fileInput = document.getElementById('media-file');
      const urlInput = document.getElementById('media-url');
      const self = this;
      document.getElementById('btn-upload').addEventListener('click', function () { fileInput.click(); });
      fileInput.addEventListener('change', function () {
        const files = Array.from(fileInput.files || []);
        if (!files.length) return;
        files.forEach(function (file) { self.uploadFile(file); });
        fileInput.value = '';
      });
      document.getElementById('btn-add-url').addEventListener('click', function () {
        self.addUrl(urlInput.value);
        urlInput.value = '';
      });
      urlInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); self.addUrl(urlInput.value); urlInput.value = ''; }
      });
    },

    isVideoUrl(url) {
      return /\.(mp4|mov|webm|avi|mkv|m4v)(\?|#|$)/i.test(url);
    },

    addUrl(raw) {
      const url = (raw || '').trim();
      if (!url) return;
      if (!/^https?:\/\//i.test(url)) {
        toast('Media URL must start with http(s)://', 'error');
        return;
      }
      const type = this.isVideoUrl(url) ? 'video' : 'image';
      this.media.push({ url: url, type: type, name: url.split('/').pop().split('?')[0] || 'media' });
      this.renderMedia();
    },

    async uploadFile(file) {
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const isVideo = ['mp4', 'mov', 'webm', 'avi', 'mkv', 'm4v'].indexOf(ext) !== -1 || file.type.indexOf('video') === 0;
      const mime = file.type || (isVideo ? 'video/mp4' : 'image/jpeg');
      toast('Uploading ' + file.name + ' \u2026', 'info');
      try {
        if (this.services.zernio) {
          const presign = await api('ajax/presign.php', {
            method: 'POST',
            body: { filename: file.name, contentType: mime },
          });
          await this.putFile(presign.uploadUrl, file, mime);
          this.media.push({ url: presign.publicUrl, type: isVideo ? 'video' : 'image', name: file.name });
        } else {
          const fd = new FormData();
          fd.append('file', file);
          const up = await api('ajax/bp_upload.php', { method: 'POST', body: fd });
          this.media.push({ url: up.url || '', type: up.type || (isVideo ? 'video' : 'image'), name: up.name || file.name, fileId: up.fileId });
        }
        this.renderMedia();
        toast(file.name + ' uploaded', 'success');
      } catch (e) {
        toast(e.message, 'error');
      }
    },

    putFile(uploadUrl, file, mime) {
      return new Promise(function (resolve, reject) {
        const xhr = new XMLHttpRequest();
        xhr.open('PUT', uploadUrl);
        xhr.setRequestHeader('Content-Type', mime);
        xhr.upload.onprogress = function (e) {
          if (e.lengthComputable) {
            const pct = Math.round((e.loaded / e.total) * 100);
            const bar = document.getElementById('upload-progress');
            if (bar) bar.style.width = pct + '%';
          }
        };
        xhr.onload = function () {
          if (xhr.status >= 200 && xhr.status < 300) resolve();
          else reject(new Error('Upload failed (HTTP ' + xhr.status + ')'));
        };
        xhr.onerror = function () { reject(new Error('Upload network error')); };
        xhr.send(file);
      });
    },

    renderMedia() {
      const list = document.getElementById('media-list');
      if (!list) return;
      const self = this;
      if (!this.media.length) {
        list.innerHTML = '<p class="text-sm text-slate-500">No media yet \u2014 upload a file or paste a URL above.</p>';
        return;
      }
      let html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">';
      this.media.forEach(function (m, i) {
        const thumb = m.type === 'image'
          ? '<img src="' + esc(m.url) + '" class="w-full h-24 object-cover rounded-lg bg-slate-800" alt="">'
          : '<div class="w-full h-24 flex items-center justify-center rounded-lg bg-slate-800 text-slate-500"><svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 0 1 0 1.971l-11.54 6.347a1.125 1.125 0 0 1-1.667-.985V5.653z" /></svg></div>';
        html += '<div class="relative border border-slate-800 rounded-xl p-2 bg-slate-900">'
          + thumb
          + '<div class="mt-2 flex items-center gap-2">'
          + '<span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded ' + (m.type === 'video' ? 'bg-fuchsia-500/15 text-fuchsia-300' : 'bg-sky-500/15 text-sky-300') + '">' + m.type + '</span>'
          + '<span class="text-xs text-slate-400 truncate flex-1">' + esc(m.name) + '</span>'
          + '<button type="button" class="text-slate-500 hover:text-rose-300" data-rm-media="' + i + '"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>'
          + '</div></div>';
      });
      html += '</div>';
      list.innerHTML = html;
      list.querySelectorAll('[data-rm-media]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          self.media.splice(Number(btn.dataset.rmMedia), 1);
          self.renderMedia();
        });
      });
    },

    bindSchedule() {
      const self = this;
      document.querySelectorAll('input[name="schedule_mode"]').forEach(function (r) {
        r.addEventListener('change', function () {
          self.schedule.mode = r.value;
          document.getElementById('schedule-fields').classList.toggle('hidden', r.value !== 'later');
        });
      });
      document.getElementById('scheduledFor').addEventListener('input', function (e) {
        self.schedule.scheduledFor = e.target.value;
      });
      document.getElementById('timezone').addEventListener('change', function (e) {
        self.schedule.timezone = e.target.value;
      });
    },

    renderPlatformOptions() {
      const wrap = document.getElementById('platform-options');
      if (!wrap) return;
      const platforms = [];
      const self = this;
      this.accounts.forEach(function (a) {
        if (self.selected.has(a._id) && platforms.indexOf(a.platform) === -1) platforms.push(a.platform);
      });
      if (!platforms.length) {
        document.getElementById('platform-options-section').classList.add('hidden');
        return;
      }
      document.getElementById('platform-options-section').classList.remove('hidden');

      const sorted = platforms.slice().sort();
      let html = '';
      sorted.forEach(function (p) {
        const cfg = PLATFORM_OPTS[p] || { fields: [] };
        const vals = self.platformValues[p] = self.platformValues[p] || {};
        html += '<div class="border border-slate-800 rounded-xl p-4 mb-4" data-platform="' + esc(p) + '">';
        html += '<div class="flex items-center gap-2 mb-3"><span class="text-sm font-semibold">' + esc(platformName(p)) + '</span></div>';
        if (cfg.note) html += '<p class="text-xs text-amber-300/90 bg-amber-500/5 border border-amber-500/20 rounded-lg px-3 py-2 mb-3">' + esc(cfg.note) + '</p>';
        cfg.fields.forEach(function (f) {
          if (f.dep && f.dep.indexOf(vals.contentType || '') === -1) return;
          html += '<div class="mb-3" data-field="' + f.k + '">';
          html += '<label class="label">' + esc(f.label) + (f.req ? ' <span class="text-rose-400">*</span>' : '') + '</label>';
          if (f.type === 'select') {
            html += '<select class="select" data-opt="' + f.k + '">';
            f.opts.forEach(function (o) {
              const sel = (vals[f.k] || f.def) === o[0] ? ' selected' : '';
              html += '<option value="' + esc(o[0]) + '"' + sel + '>' + esc(o[1]) + '</option>';
            });
            html += '</select>';
          } else if (f.type === 'toggle') {
            const checked = vals[f.k] !== undefined ? vals[f.k] : !!f.def;
            html += '<label class="flex items-center gap-2 cursor-pointer">'
              + '<input type="checkbox" class="h-4 w-4 rounded border-slate-600 text-violet-500 bg-slate-800 focus:ring-violet-500 focus:ring-offset-0" data-opt="' + f.k + '"' + (checked ? ' checked' : '') + '>'
              + '<span class="text-sm text-slate-300">' + esc(f.label) + '</span></label>';
          } else {
            html += '<input type="text" class="input" data-opt="' + f.k + '" placeholder="' + esc(f.ph || '') + '" value="' + esc(vals[f.k] || '') + '">';
          }
          html += '</div>';
        });
        html += '<div class="mb-2"><label class="label">Custom content (only ' + esc(platformName(p)) + ')</label>'
          + '<textarea class="textarea h-20" data-opt="customContent" placeholder="Overrides the main caption for this platform">' + esc(vals.customContent || '') + '</textarea></div>';
        html += '</div>';
      });
      wrap.innerHTML = html;
      wrap.querySelectorAll('[data-opt]').forEach(function (el) {
        const panel = el.closest('[data-platform]');
        if (!panel) return;
        const plat = panel.dataset.platform;
        const k = el.dataset.opt;
        const apply = function () {
          self.platformValues[plat][k] = el.type === 'checkbox' ? el.checked : el.value;
          self.renderPlatformOptionsDeps(plat);
        };
        el.addEventListener('input', apply);
        el.addEventListener('change', apply);
      });
    },

    renderPlatformOptionsDeps(plat) {
      const cfg = PLATFORM_OPTS[plat] || { fields: [] };
      const vals = this.platformValues[plat] || {};
      cfg.fields.forEach(function (f) {
        if (!f.dep) return;
        const show = f.dep.indexOf(vals.contentType || '') !== -1;
        const panel = document.querySelector('#platform-options [data-platform="' + plat + '"]');
        if (!panel) return;
        const field = panel.querySelector('[data-field="' + f.k + '"]');
        if (field) field.style.display = show ? '' : 'none';
      });
    },

    buildPayload() {
      const self = this;
      const platforms = [];
      this.accounts.forEach(function (a) {
        if (!self.selected.has(a._id)) return;
        platforms.push(self.buildPlatformEntry(a));
      });
      const payload = {
        content: (document.getElementById('caption').value || '').trim() || undefined,
        platforms: platforms,
      };
      if (this.media.length) {
        payload.mediaItems = this.media.map(function (m) {
          const item = { url: m.url, type: m.type };
          if (m.fileId) item.fileId = m.fileId;
          return item;
        });
        // attach youtube thumbnail to first video media item
        const yt = this.platformValues['youtube'];
        if (yt && yt.thumbnail) {
          const vid = payload.mediaItems.find(function (m) { return m.type === 'video'; });
          if (vid) vid.thumbnail = yt.thumbnail;
        }
      }
      const tags = [];
      const ytv = this.platformValues['youtube'];
      if (ytv && ytv.tags) {
        ytv.tags.split(',').forEach(function (t) {
          t = t.trim();
          if (t) tags.push(t);
        });
      }
      if (tags.length) payload.tags = tags;

      if (this.schedule.mode === 'later') {
        if (!this.schedule.scheduledFor) throw new Error('Pick a date/time to schedule the post');
        payload.scheduledFor = this.schedule.scheduledFor + ':00';
        payload.timezone = this.schedule.timezone || 'UTC';
      } else {
        payload.publishNow = true;
      }

      const tiktok = this.platformValues['tiktok'];
      const zernioTikTok = platforms.some(function (p) {
        return p.platform === 'tiktok' && p.service !== 'bulkpublish';
      });
      if (zernioTikTok) {
        payload.tiktokSettings = {
          privacy_level: (tiktok && tiktok.privacy_level) || 'PUBLIC_TO_EVERYONE',
          allow_comment: !(tiktok && tiktok.allow_comment === false),
          allow_duet: !(tiktok && tiktok.allow_duet === false),
          allow_stitch: !(tiktok && tiktok.allow_stitch === false),
          content_preview_confirmed: true,
          express_consent_given: true,
        };
        if (tiktok && tiktok.video_cover_image_url) payload.tiktokSettings.video_cover_image_url = tiktok.video_cover_image_url;
      }
      return payload;
    },

    buildPlatformEntry(acc) {
      const p = acc.platform;
      const vals = this.platformValues[p] || {};
      const isBp = acc.service === 'bulkpublish';
      const entry = { service: acc.service, platform: p };
      if (isBp) {
        entry.channelId = acc.channelId;
      } else {
        entry.accountId = acc._id;
      }

      const psd = {};
      if (p === 'youtube') {
        if (vals.title) psd.title = vals.title;
        if (vals.visibility) {
          if (isBp) psd.privacyStatus = vals.visibility;
          else psd.visibility = vals.visibility;
        }
        if (vals.categoryId) psd.categoryId = vals.categoryId;
        if (vals.madeForKids) psd.madeForKids = true;
        if (vals.firstComment) psd.firstComment = vals.firstComment;
      } else if (p === 'instagram') {
        if (isBp) {
          if (vals.contentType === 'story') entry.postTypeOverride = 'story';
          if (vals.contentType === 'reel') entry.postTypeOverride = 'reel';
        } else if (vals.contentType === 'story') {
          psd.contentType = 'story';
        }
        if (vals.firstComment) psd.firstComment = vals.firstComment;
      } else if (p === 'facebook') {
        if (isBp) {
          if (vals.contentType === 'reel') entry.postTypeOverride = 'reel';
          else if (vals.contentType === 'story') entry.postTypeOverride = 'story';
          else entry.postTypeOverride = 'post';
          if (vals.contentType === 'reel' && vals.title) psd.title = vals.title;
        } else {
          if (vals.contentType && vals.contentType !== 'feed') psd.contentType = vals.contentType;
          if (vals.contentType === 'reel' && vals.title) psd.title = vals.title;
        }
        if (vals.firstComment) psd.firstComment = vals.firstComment;
      } else if (p === 'linkedin') {
        if (vals.firstComment) psd.firstComment = vals.firstComment;
      } else if (p === 'twitter' && vals.replySettings) {
        psd.replySettings = vals.replySettings;
      } else if (p === 'tiktok') {
        if (isBp) {
          if (vals.privacy_level) psd.privacyLevel = vals.privacy_level;
          if (vals.allow_comment === false) psd.disableComment = true;
          if (vals.allow_duet === false) psd.disableDuet = true;
          if (vals.allow_stitch === false) psd.disableStitch = true;
          if (vals.video_cover_image_url) psd.thumbnailUrl = vals.video_cover_image_url;
        }
      }
      if (isBp && vals.firstComment) psd._firstComment = vals.firstComment;
      if (Object.keys(psd).length) {
        if (isBp) entry.platformSpecific = psd;
        else entry.platformSpecificData = psd;
      }
      if (vals.customContent) entry.customContent = vals.customContent;
      return entry;
    },

    bindSubmit(form) {
      const self = this;
      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btn-submit');
        const label = btn.querySelector('span');
        if (self.selected.size === 0) {
          toast('Select at least one channel', 'error');
          return;
        }
        if (self.postType === 'caption') {
          const cap = (document.getElementById('caption').value || '').trim();
          if (!cap) { toast('A caption is required for a text-only post', 'error'); return; }
        }
        const yt = self.platformValues['youtube'];
        const hasYT = Array.from(self.selected).some(function (id) {
          const a = self.accounts.find(function (x) { return x._id === id; });
          return a && a.platform === 'youtube';
        });
        if (hasYT && yt && !yt.title) { toast('YouTube requires a video title', 'error'); return; }

        let payload;
        try {
          payload = self.buildPayload();
        } catch (err) {
          toast(err.message, 'error');
          return;
        }
        btn.disabled = true;
        if (label) label.textContent = 'Submitting \u2026';
        try {
          const data = await api('ajax/create_post.php', { method: 'POST', body: payload });
          const posts = data.posts || [];
          const names = posts.map(function (p) {
            const id = p.service === 'bulkpublish' ? (p.post && p.post.id) : (p.post && p.post._id);
            return p.service + ': ' + id;
          }).join(', ');
          toast('Posted successfully' + (names ? ' \u2014 ' + names : ''), 'success');
          if (btn) { btn.disabled = false; if (label) label.textContent = 'Submit post'; }
          setTimeout(function () { window.location.href = 'posts.php'; }, 1500);
        } catch (err) {
          toast(err.message, 'error');
          if (btn) { btn.disabled = false; if (label) label.textContent = 'Submit post'; }
        }
      });
    },
  };

  /* ---------------- posts list (filters) ---------------- */

  function initPosts() {
    const form = document.getElementById('filter-form');
    if (!form) return;
    form.querySelectorAll('select, input').forEach(function (el) {
      if (el.type === 'text') {
        el.addEventListener('change', function () { form.submit(); });
      } else {
        el.addEventListener('change', function () { form.submit(); });
      }
    });
  }

  /* ---------------- post view (retry / unpublish) ---------------- */

  function initPostView() {
    const retry = document.getElementById('btn-retry');
    const unpub = document.getElementById('btn-unpublish');
    const publishNow = document.getElementById('btn-publish-now');
    const svcOf = function (btn) { return btn ? (btn.dataset.service || 'zernio') : 'zernio'; };
    if (retry) retry.addEventListener('click', function () {
      if (!confirm('Retry this failed post?')) return;
      api('ajax/action.php', { method: 'POST', body: { action: 'retry', postId: retry.dataset.id, service: svcOf(retry) } })
        .then(function () { toast('Retry queued'); setTimeout(function () { location.reload(); }, 900); })
        .catch(function (e) { toast(e.message, 'error'); });
    });
    if (unpub) unpub.addEventListener('click', function () {
      if (!confirm('Unpublish this post from all platforms?')) return;
      api('ajax/action.php', { method: 'POST', body: { action: 'unpublish', postId: unpub.dataset.id, service: 'zernio' } })
        .then(function () { toast('Post unpublished'); setTimeout(function () { location.reload(); }, 900); })
        .catch(function (e) { toast(e.message, 'error'); });
    });
    if (publishNow) publishNow.addEventListener('click', function () {
      if (!confirm('Publish this draft now?')) return;
      api('ajax/action.php', { method: 'POST', body: { action: 'publish', postId: publishNow.dataset.id, service: 'bulkpublish' } })
        .then(function () { toast('Publishing started'); setTimeout(function () { location.reload(); }, 900); })
        .catch(function (e) { toast(e.message, 'error'); });
    });
  }

  /* ---------------- bulk upload ---------------- */

  function initBulk() {
    const fileInput = document.getElementById('bulk-file');
    const runBtn = document.getElementById('btn-bulk-run');
    const results = document.getElementById('bulk-results');
    if (!fileInput || !runBtn) return;

    document.getElementById('btn-bulk-pick').addEventListener('click', function () { fileInput.click(); });

    const self = { file: null };
    fileInput.addEventListener('change', function () {
      self.file = fileInput.files[0];
      document.getElementById('bulk-file-name').textContent = self.file ? self.file.name : 'No file selected';
    });

    runBtn.addEventListener('click', function () {
      if (!self.file) { toast('Choose a CSV file first', 'error'); return; }
      const dry = document.getElementById('bulk-dry').checked;
      const fd = new FormData();
      fd.append('file', self.file);
      fd.append('dryRun', dry ? '1' : '0');
      runBtn.disabled = true;
      runBtn.querySelector('span').textContent = dry ? 'Validating \u2026' : 'Publishing \u2026';
      results.innerHTML = '<div class="p-6 text-center text-sm text-slate-400">Processing \u2026</div>';
      api('ajax/bulk_upload.php', { method: 'POST', body: fd })
        .then(function (data) {
          renderBulkResults(results, data, dry);
          runBtn.disabled = false;
          runBtn.querySelector('span').textContent = dry ? 'Validate (dry run)' : 'Publish all';
        })
        .catch(function (e) {
          results.innerHTML = '<div class="p-4 text-sm text-rose-300 border border-rose-500/30 rounded-lg">' + esc(e.message) + '</div>';
          runBtn.disabled = false;
          runBtn.querySelector('span').textContent = dry ? 'Validate (dry run)' : 'Publish all';
        });
    });
  }

  function renderBulkResults(el, data, dry) {
    const results = data.results || [];
    let ok = 0, bad = 0;
    results.forEach(function (r) { if (r.ok) ok++; else bad++; });
    let html = '<div class="grid grid-cols-3 gap-3 mb-4">'
      + '<div class="p-4 rounded-xl border border-slate-800 bg-slate-900 text-center"><div class="text-2xl font-bold">' + (data.total || 0) + '</div><div class="text-xs text-slate-500">Rows</div></div>'
      + '<div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5 text-center"><div class="text-2xl font-bold text-emerald-300">' + (data.valid || 0) + '</div><div class="text-xs text-slate-500">' + (dry ? 'Valid' : 'Created') + '</div></div>'
      + '<div class="p-4 rounded-xl border border-rose-500/30 bg-rose-500/5 text-center"><div class="text-2xl font-bold text-rose-300">' + (data.invalid || 0) + '</div><div class="text-xs text-slate-500">Failed</div></div>'
      + '</div>';
    if (data.warnings && data.warnings.length) {
      data.warnings.forEach(function (w) { html += '<p class="text-xs text-amber-300 mb-2">' + esc(w) + '</p>'; });
    }
    if (results.length) {
      html += '<table class="table"><thead><tr><th>#</th><th>Result</th><th>Details</th></tr></thead><tbody>';
      results.forEach(function (r) {
        const svcTag = r.service === 'bulkpublish'
          ? '<span class="text-[9px] font-bold px-1 py-0.5 rounded bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25">BP</span> '
          : '<span class="text-[9px] font-bold px-1 py-0.5 rounded bg-violet-500/15 text-violet-300 border border-violet-500/25">ZN</span> ';
        const txt = r.ok
          ? (dry ? 'OK' : 'Created: ' + svcTag + '<code class="text-emerald-300">' + esc(r.createdPostId || '') + '</code>')
          : '<span class="text-rose-300">' + esc((r.errors || []).join(', ')) + '</span>';
        html += '<tr><td class="text-slate-500">' + r.rowIndex + '</td><td>' + (r.ok ? '<span class="text-emerald-300 font-medium">&#10003;</span>' : '<span class="text-rose-300 font-medium">&#10007;</span>') + '</td><td>' + txt + '</td></tr>';
      });
      html += '</tbody></table>';
    }
    el.innerHTML = html;
  }

  /* ---------------- init ---------------- */

  document.addEventListener('DOMContentLoaded', function () {
    composer.init();
    initPosts();
    initPostView();
    initBulk();
  });

  // Small global for pages that render server-side but want toasts.
  window.PostStudio = { toast: toast, api: api, composer: composer };
})();