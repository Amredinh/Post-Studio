<?php
$page_title = 'Bulk Publish';
$active = 'bulk';
require_once __DIR__ . '/includes/header.php';

$hasKey = (bool)get_setting('zernio_api_key', '') || (bool)get_setting('bulkpublish_api_key', '');
?>
<?php if (!$hasKey): ?>
  <div class="card card-pad flex flex-col sm:flex-row items-start sm:items-center gap-4 justify-between">
    <div>
      <h2 class="text-lg font-semibold">API key required</h2>
      <p class="text-sm text-slate-400 mt-1">Configure a Zernio and/or BulkPublish API key before bulk publishing.</p>
    </div>
    <a href="settings.php" class="btn btn-primary shrink-0">Go to Settings</a>
  </div>
<?php else: ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">

    <div class="card card-pad">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold">Upload CSV</h2>
        <a href="assets/csv_template.csv" download class="btn btn-ghost btn-sm">Download template</a>
      </div>
      <p class="text-sm text-slate-500 mb-5">
        Each row creates one post. Columns:
        <code class="text-violet-300 text-xs">service, platform, username, caption, media_url, media_type, scheduled_for, timezone, custom_content</code>
        (service is <code class="text-violet-300">zernio</code> or <code class="text-violet-300">bulkpublish</code>; default <code class="text-violet-300">zernio</code>)
      </p>
      <ul class="text-xs text-slate-500 space-y-1 mb-5 list-disc list-inside">
        <li><code class="text-violet-300">media_type</code>: <code class="text-violet-300">caption</code> (no media), <code class="text-violet-300">image</code>, or <code class="text-violet-300">video</code></li>
        <li><code class="text-violet-300">scheduled_for</code>: <code class="text-violet-300">YYYY-MM-DD HH:MM</code> in your chosen timezone; leave empty to publish now</li>
        <li><code class="text-violet-300">username</code> must match a connected account exactly (e.g. <code class="text-violet-300">@acme</code>)</li>
        <li>Leave <code class="text-violet-300">custom_content</code> empty to use the main caption everywhere</li>
      </ul>

      <div class="flex flex-col sm:flex-row gap-3 items-start">
        <button type="button" id="btn-bulk-pick" class="btn btn-ghost">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
          Choose CSV file
        </button>
        <input type="file" id="bulk-file" class="hidden" accept=".csv,text/csv">
        <span id="bulk-file-name" class="text-sm text-slate-400 self-center">No file selected</span>
      </div>

      <div class="mt-5 flex flex-col sm:flex-row gap-3 items-center justify-between">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" id="bulk-dry" class="h-4 w-4 rounded border-slate-600 text-violet-500 bg-slate-800 focus:ring-violet-500 focus:ring-offset-0">
          <span class="text-sm text-slate-300">Validate only (dry run — no posts created)</span>
        </label>
        <button type="button" id="btn-bulk-run" class="btn btn-primary px-6">
          <span>Publish all</span>
        </button>
      </div>
    </div>

    <div class="card card-pad" id="bulk-results-wrap">
      <h2 class="text-base font-semibold mb-4">Results</h2>
      <div id="bulk-results">
        <p class="text-sm text-slate-500">Upload a CSV to see per-row results here. Use dry run to validate before publishing.</p>
      </div>
    </div>
  </div>

  <div>
    <div class="card card-pad">
      <h2 class="text-base font-semibold mb-3">Tips</h2>
      <ul class="text-sm text-slate-400 space-y-2.5">
        <li class="flex gap-2"><span class="text-violet-400">•</span> Run a <strong class="text-slate-200">dry run</strong> first to catch bad usernames or media types.</li>
        <li class="flex gap-2"><span class="text-violet-400">•</span> Use the same <code class="text-violet-300">media_url</code> across rows to reuse one upload.</li>
        <li class="flex gap-2"><span class="text-violet-400">•</span> BulkPublish rows must reference an account exactly as shown in its dashboard (matches <code class="text-violet-300">accountName</code>).</li>
        <li class="flex gap-2"><span class="text-violet-400">•</span> Media URLs must be publicly accessible files, not Google Drive/Dropbox pages.</li>
        <li class="flex gap-2"><span class="text-violet-400">•</span> Video type (Shorts/Reel/Story) is set per platform in the composer; for bulk, use the correct aspect-ratio file and it is auto-detected.</li>
      </ul>
    </div>
  </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>