<?php
$page_title = 'Post Details';
$active = 'posts';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/zernio.php';
require_once __DIR__ . '/includes/bulkpublish.php';

$postId = trim($_GET['id'] ?? '');
$service = trim($_GET['service'] ?? '') === 'bulkpublish' ? 'bulkpublish' : 'zernio';
$view = null;
$error = null;

if ($postId !== '') {
    try {
        if ($service === 'bulkpublish') {
            $bp = bulkpublish_client();
            if (!$bp) {
                $error = 'No BulkPublish API key configured.';
            } else {
                $rawId = preg_replace('/^b/', '', $postId);
                $post = $bp->getPost($rawId)['post'] ?? $bp->getPost($rawId);
                $view = normalize_bp_view($post);
            }
        } else {
            $zernio = zernio_client();
            if (!$zernio) {
                $error = 'No Zernio API key configured.';
            } else {
                $post = $zernio->getPost($postId)['post'] ?? null;
                $view = normalize_zernio_view($post);
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

function normalize_zernio_view(array $post): array {
    $media = [];
    foreach (($post['mediaItems'] ?? []) as $m) {
        $media[] = ['url' => $m['url'] ?? '', 'type' => $m['type'] ?? 'image'];
    }
    $platforms = [];
    foreach (($post['platforms'] ?? []) as $pl) {
        $platforms[] = [
            'platform' => $pl['platform'] ?? '',
            'status' => $pl['status'] ?? '',
            'name' => $pl['accountId']['displayName'] ?? $pl['accountId']['username'] ?? '',
            'username' => $pl['accountId']['username'] ?? '',
            'url' => $pl['platformPostUrl'] ?? '',
            'error' => $pl['error'] ?? '',
        ];
    }
    return [
        'service' => 'zernio',
        '_id' => $post['_id'] ?? '',
        'title' => $post['title'] ?? 'Untitled post',
        'content' => $post['content'] ?? '',
        'status' => $post['status'] ?? '',
        'scheduledFor' => $post['scheduledFor'] ?? null,
        'timezone' => $post['timezone'] ?? null,
        'mediaItems' => $media,
        'tags' => $post['tags'] ?? [],
        'platforms' => $platforms,
    ];
}

function normalize_bp_view(array $post): array {
    $media = [];
    foreach (($post['mediaFiles'] ?? []) as $m) {
        $media[] = ['url' => $m['originalUrl'] ?? ($m['previewUrl'] ?? ''), 'type' => strpos($m['mimeType'] ?? '', 'video') === 0 ? 'video' : 'image'];
    }
    $platforms = [];
    foreach (($post['postPlatforms'] ?? []) as $pl) {
        $platforms[] = [
            'platform' => bp_map_platform((string)($pl['platform'] ?? '')),
            'status' => $pl['status'] ?? '',
            'name' => '',
            'username' => '',
            'url' => $pl['platformUrl'] ?? '',
            'error' => $pl['errorMessage'] ?? '',
        ];
    }
    return [
        'service' => 'bulkpublish',
        '_id' => 'b' . ($post['id'] ?? ''),
        'title' => 'Untitled post',
        'content' => $post['content'] ?? '',
        'status' => $post['status'] ?? '',
        'scheduledFor' => $post['scheduledAt'] ?? null,
        'timezone' => $post['timezone'] ?? null,
        'mediaItems' => $media,
        'tags' => [],
        'platforms' => $platforms,
    ];
}
?>
<?php if ($error): ?>
  <div class="card card-pad text-sm text-rose-200 bg-rose-500/10 border border-rose-500/30"><?= htmlspecialchars($error) ?></div>
<?php elseif (!$view): ?>
  <div class="card card-pad text-sm text-slate-400">Post not found.</div>
<?php else: ?>

<a href="posts.php" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-violet-300 mb-4">
  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
  Back to posts
</a>

<div class="card overflow-hidden">
  <div class="px-6 py-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
    <div>
      <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold"><?= htmlspecialchars($view['title']) ?></h2>
        <?= status_badge($view['status']) ?>
        <?= $view['service'] === 'bulkpublish' ? '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25">BulkPublish</span>' : '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-violet-500/15 text-violet-300 border border-violet-500/25">Zernio</span>' ?>
      </div>
      <div class="text-xs text-slate-500 font-mono mt-1"><?= htmlspecialchars($view['_id']) ?></div>
    </div>
    <div class="flex gap-2">
      <?php if (($view['status'] ?? '') === 'failed'): ?>
        <button type="button" id="btn-retry" data-id="<?= htmlspecialchars($view['_id']) ?>" data-service="<?= $view['service'] ?>" class="btn btn-primary btn-sm">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
          Retry
        </button>
      <?php endif; ?>
      <?php if (in_array($view['status'] ?? '', ['published', 'partial'], true) && $view['service'] === 'zernio'): ?>
        <button type="button" id="btn-unpublish" data-id="<?= htmlspecialchars($view['_id']) ?>" data-service="zernio" class="btn btn-danger btn-sm">
          Unpublish
        </button>
      <?php endif; ?>
      <?php if (($view['status'] ?? '') === 'draft' && $view['service'] === 'bulkpublish'): ?>
        <button type="button" id="btn-publish-now" data-id="<?= htmlspecialchars($view['_id']) ?>" data-service="bulkpublish" class="btn btn-primary btn-sm">
          Publish now
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="px-6 py-5 grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div>
      <label class="label mb-2">Caption</label>
      <p class="text-sm text-slate-300 whitespace-pre-wrap"><?= htmlspecialchars((string)($view['content'] ?? '')) ?: '<span class="text-slate-600">(none)</span>' ?></p>

      <?php if (!empty($view['mediaItems'])): ?>
        <label class="label mt-6 mb-2">Media</label>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <?php foreach ($view['mediaItems'] as $m): ?>
            <a href="<?= htmlspecialchars($m['url'] ?? '#') ?>" target="_blank" rel="noopener" class="block">
              <?php if (($m['type'] ?? '') === 'image'): ?>
                <img src="<?= htmlspecialchars($m['url']) ?>" class="w-full h-24 object-cover rounded-lg bg-slate-800 border border-slate-800" alt="">
              <?php else: ?>
                <div class="w-full h-24 flex items-center justify-center rounded-lg bg-slate-800 border border-slate-800 text-slate-500">
                  <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 0 1 0 1.971l-11.54 6.347a1.125 1.125 0 0 1-1.667-.985V5.653z" /></svg>
                </div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($view['tags'])): ?>
        <label class="label mt-6 mb-2">Tags</label>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach ($view['tags'] as $t): ?>
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300"><?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <label class="label mb-2">Schedule</label>
      <div class="space-y-1.5 text-sm text-slate-300">
        <div><span class="text-slate-500">Status:</span> <?= status_badge($view['status']) ?></div>
        <div><span class="text-slate-500">Scheduled for:</span> <?= !empty($view['scheduledFor']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($view['scheduledFor']))) : 'Immediately' ?></div>
        <?php if (!empty($view['timezone'])): ?><div><span class="text-slate-500">Timezone:</span> <?= htmlspecialchars($view['timezone']) ?></div><?php endif; ?>
      </div>

      <label class="label mt-6 mb-2">Platform results</label>
      <div class="space-y-2">
        <?php foreach (($view['platforms'] ?? []) as $pl): $meta = platform_meta($pl['platform']); ?>
          <div class="border border-slate-800 rounded-xl p-3">
            <div class="flex items-center gap-2.5">
              <span class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold flex-none <?= $meta['dark'] ? 'text-slate-900' : 'text-white' ?>" style="background:<?= htmlspecialchars($meta['color']) ?>"><?= htmlspecialchars($meta['short']) ?></span>
              <div class="min-w-0 flex-1">
                <div class="text-sm font-medium truncate"><?= htmlspecialchars($pl['name'] ?: $meta['name']) ?></div>
                <?php if (!empty($pl['username'])): ?><div class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($pl['username']) ?></div><?php endif; ?>
              </div>
              <?= status_badge($pl['status'] ?? '') ?>
            </div>
            <?php if (!empty($pl['url'])): ?>
              <a href="<?= htmlspecialchars($pl['url']) ?>" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-xs text-violet-400 hover:text-violet-300">
                View on <?= htmlspecialchars($meta['name']) ?>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
              </a>
            <?php endif; ?>
            <?php if (!empty($pl['error'])): ?>
              <div class="mt-2 text-xs text-rose-300 bg-rose-500/10 border border-rose-500/20 rounded-lg px-2.5 py-1.5"><?= htmlspecialchars((string)$pl['error']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
