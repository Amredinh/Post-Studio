<?php
$page_title = 'Compose Post';
$active = 'composer';
require_once __DIR__ . '/includes/header.php';

$hasKey = (bool)get_setting('zernio_api_key', '') || (bool)get_setting('bulkpublish_api_key', '');
$zones = DateTimeZone::listIdentifiers();
?>
<?php if (!$hasKey): ?>
  <div class="card card-pad flex flex-col sm:flex-row items-start sm:items-center gap-4 justify-between">
    <div>
      <h2 class="text-lg font-semibold">API key required</h2>
      <p class="text-sm text-slate-400 mt-1">Configure a Zernio and/or BulkPublish API key before composing posts.</p>
    </div>
    <a href="settings.php" class="btn btn-primary shrink-0">Go to Settings</a>
  </div>
<?php else: ?>

<form id="composer-form" class="space-y-6">

  <!-- 1. Post type -->
  <div class="card card-pad">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold">1 · Post type</h2>
      <span class="text-xs text-slate-500">Video, image, or caption only</span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <label class="option-card type-card p-4 cursor-pointer selected">
        <input type="radio" name="post_type" value="caption" class="hidden" checked>
        <div class="flex items-center gap-3">
          <span class="w-9 h-9 rounded-lg bg-sky-500/15 text-sky-300 flex items-center justify-center flex-none">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>
          </span>
          <div>
            <div class="font-medium text-sm">Caption only</div>
            <div class="text-[11px] text-slate-500">Text / link post</div>
          </div>
        </div>
      </label>
      <label class="option-card type-card p-4 cursor-pointer">
        <input type="radio" name="post_type" value="image" class="hidden">
        <div class="flex items-center gap-3">
          <span class="w-9 h-9 rounded-lg bg-emerald-500/15 text-emerald-300 flex items-center justify-center flex-none">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21z" /></svg>
          </span>
          <div>
            <div class="font-medium text-sm">Image</div>
            <div class="text-[11px] text-slate-500">Single or carousel + caption</div>
          </div>
        </div>
      </label>
      <label class="option-card type-card p-4 cursor-pointer">
        <input type="radio" name="post_type" value="video" class="hidden">
        <div class="flex items-center gap-3">
          <span class="w-9 h-9 rounded-lg bg-fuchsia-500/15 text-fuchsia-300 flex items-center justify-center flex-none">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z" /></svg>
          </span>
          <div>
            <div class="font-medium text-sm">Video</div>
            <div class="text-[11px] text-slate-500">Reels, Shorts, standard video</div>
          </div>
        </div>
      </label>
    </div>
  </div>

  <!-- 2. Media -->
  <div id="media-section" class="card card-pad hidden">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold">2 · Media</h2>
      <span class="text-xs text-slate-500">Upload or paste a public URL</span>
    </div>
    <div class="flex flex-col sm:flex-row gap-3">
      <input type="file" id="media-file" class="hidden" accept="image/*,video/*" multiple>
      <button type="button" id="btn-upload" class="btn btn-ghost">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
        Upload file
      </button>
      <input type="text" id="media-url" class="input flex-1" placeholder="or paste a media URL (https://...)">
      <button type="button" id="btn-add-url" class="btn btn-ghost">Add URL</button>
    </div>
    <div class="mt-3">
      <div class="progress-wrap"><div id="upload-progress" class="progress-bar" style="width:0%"></div></div>
    </div>
    <div id="media-list" class="mt-2">
      <p class="text-sm text-slate-500">No media yet — upload a file or paste a URL above.</p>
    </div>
  </div>

  <!-- 3. Caption -->
  <div class="card card-pad">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold">3 · Caption</h2>
      <span class="text-xs text-slate-500" id="caption-label">Caption (optional)</span>
    </div>
    <textarea id="caption" class="textarea" placeholder="Write your caption here…"></textarea>
  </div>

  <!-- 4. Schedule -->
  <div class="card card-pad">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold">4 · Schedule</h2>
      <span class="text-xs text-slate-500">Publish now or any future time</span>
    </div>
    <div class="flex gap-6">
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" name="schedule_mode" value="now" class="h-4 w-4 text-violet-500 border-slate-600 bg-slate-800 focus:ring-violet-500 focus:ring-offset-0" checked>
        <span class="text-sm">Publish now</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="radio" name="schedule_mode" value="later" class="h-4 w-4 text-violet-500 border-slate-600 bg-slate-800 focus:ring-violet-500 focus:ring-offset-0">
        <span class="text-sm">Schedule for later</span>
      </label>
    </div>
    <div id="schedule-fields" class="hidden mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="label">Date &amp; time</label>
        <input type="datetime-local" id="scheduledFor" class="input">
      </div>
      <div>
        <label class="label">Timezone</label>
        <select id="timezone" class="select">
          <?php foreach ($zones as $z): ?>
            <option value="<?= htmlspecialchars($z) ?>" <?= $z === 'UTC' ? 'selected' : '' ?>><?= htmlspecialchars($z) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- 5. Channels -->
  <div class="card card-pad">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold">5 · Select channels</h2>
      <span class="text-xs text-slate-500">Detected from your Zernio + BulkPublish accounts</span>
    </div>
    <div id="accounts-grid">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div class="skeleton h-16"></div><div class="skeleton h-16"></div><div class="skeleton h-16"></div>
      </div>
    </div>
  </div>

  <!-- 6. Platform options -->
  <div class="card card-pad hidden" id="platform-options-section">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold">6 · Platform options</h2>
      <span class="text-xs text-slate-500">Format, title, privacy &amp; video type per channel</span>
    </div>
    <div id="platform-options"></div>
  </div>

  <div class="flex items-center justify-end gap-3">
    <button type="submit" id="btn-submit" class="btn btn-primary px-8 py-3">
      <span>Submit post</span>
    </button>
  </div>
</form>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>