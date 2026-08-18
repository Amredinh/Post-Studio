<?php
$page_title = 'Posts';
$active = 'posts';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/zernio.php';
require_once __DIR__ . '/includes/bulkpublish.php';

$hasZernio = (bool)get_setting('zernio_api_key', '');
$hasBp = (bool)get_setting('bulkpublish_api_key', '');
$hasKey = $hasZernio || $hasBp;
$error = null;
$posts = [];
$pagination = null;

$filters = [
    'status'    => trim($_GET['status'] ?? ''),
    'platform'  => trim($_GET['platform'] ?? ''),
    'search'    => trim($_GET['search'] ?? ''),
    'dateFrom'  => trim($_GET['dateFrom'] ?? ''),
    'dateTo'    => trim($_GET['dateTo'] ?? ''),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$fetchLimit = 100; // fetch a batch from each service (page 1), then merge+paginate locally

if ($hasKey) {
    $items = [];

    if ($hasZernio) {
        try {
            $zQuery = ['page' => 1, 'limit' => $fetchLimit];
            if ($filters['status'] !== '') $zQuery['status'] = $filters['status'];
            if ($filters['platform'] !== '') $zQuery['platform'] = $filters['platform'];
            if ($filters['search'] !== '') $zQuery['search'] = $filters['search'];
            if ($filters['dateFrom'] !== '') $zQuery['dateFrom'] = $filters['dateFrom'];
            if ($filters['dateTo'] !== '') $zQuery['dateTo'] = $filters['dateTo'];
            $zernio = new Zernio((string)get_setting('zernio_api_key', ''));
            $data = $zernio->listPosts($zQuery);
            foreach (($data['posts'] ?? []) as $p) {
                $items[] = normalize_zernio_post($p);
            }
        } catch (Throwable $e) {
            $error = 'Zernio: ' . $e->getMessage();
        }
    }

    if ($hasBp) {
        try {
            $bq = ['page' => 1, 'limit' => $fetchLimit];
            if ($filters['status'] !== '') $bq['status'] = $filters['status'];
            if ($filters['search'] !== '') $bq['search'] = $filters['search'];
            if ($filters['dateFrom'] !== '') $bq['from'] = $filters['dateFrom'];
            if ($filters['dateTo'] !== '') $bq['to'] = $filters['dateTo'];
            $bp = new BulkPublish((string)get_setting('bulkpublish_api_key', ''));
            $data = $bp->listPosts($bq);
            foreach (($data['posts'] ?? []) as $p) {
                if ($filters['platform'] !== '') {
                    $hit = false;
                    foreach (($p['postPlatforms'] ?? []) as $pp) {
                        if (bp_map_platform((string)($pp['platform'] ?? '')) === $filters['platform']) { $hit = true; break; }
                    }
                    if (!$hit) continue;
                }
                $items[] = normalize_bp_post($p);
            }
        } catch (Throwable $e) {
            $error = trim(($error ? $error . ' | ' : '') . 'BulkPublish: ' . $e->getMessage());
        }
    }

    // Sort newest first by scheduled time (falls back to creation time).
    usort($items, function ($a, $b) {
        $ta = $a['_sort'] ?? '';
        $tb = $b['_sort'] ?? '';
        return strcmp($tb, $ta);
    });

    $total = count($items);
    $totalPages = max(1, (int)ceil($total / $limit));
    $offset = ($page - 1) * $limit;
    $posts = array_slice($items, $offset, $limit);
    $pagination = ['total' => $total, 'totalPages' => $totalPages, 'page' => $page, 'limit' => $limit];
}

function normalize_zernio_post(array $p): array {
    $platforms = [];
    foreach (($p['platforms'] ?? []) as $pl) {
        $platforms[] = [
            'platform' => $pl['platform'] ?? '',
            'status' => $pl['status'] ?? '',
            'url' => $pl['platformPostUrl'] ?? '',
            'error' => $pl['error'] ?? '',
            'name' => $pl['accountId']['displayName'] ?? $pl['accountId']['username'] ?? '',
        ];
    }
    return [
        '_id' => $p['_id'] ?? '',
        'service' => 'zernio',
        'content' => $p['content'] ?? ($p['title'] ?? ''),
        'status' => $p['status'] ?? '',
        'scheduledFor' => $p['scheduledFor'] ?? null,
        'mediaItems' => $p['mediaItems'] ?? [],
        'platforms' => $platforms,
        '_sort' => $p['scheduledFor'] ?? ($p['createdAt'] ?? ''),
    ];
}

function normalize_bp_post(array $p): array {
    $platforms = [];
    foreach (($p['postPlatforms'] ?? []) as $pl) {
        $platforms[] = [
            'platform' => bp_map_platform((string)($pl['platform'] ?? '')),
            'status' => $pl['status'] ?? '',
            'url' => $pl['platformUrl'] ?? '',
            'error' => $pl['errorMessage'] ?? '',
            'name' => '',
        ];
    }
    $media = [];
    foreach (($p['mediaFiles'] ?? []) as $m) {
        $media[] = ['url' => $m['originalUrl'] ?? '', 'type' => strpos($m['mimeType'] ?? '', 'video') === 0 ? 'video' : 'image'];
    }
    return [
        '_id' => 'b' . ($p['id'] ?? ''),
        'service' => 'bulkpublish',
        'content' => $p['content'] ?? '',
        'status' => $p['status'] ?? '',
        'scheduledFor' => $p['scheduledAt'] ?? null,
        'mediaItems' => $media,
        'platforms' => $platforms,
        '_sort' => $p['scheduledAt'] ?? ($p['createdAt'] ?? ''),
    ];
}

function post_preview(array $p, int $len = 120): string {
    $txt = $p['content'] ?? $p['title'] ?? '';
    $txt = trim((string)$txt);
    if ($txt === '') return '<span class="text-slate-600">(no caption)</span>';
    if (function_exists('mb_strlen') && mb_strlen($txt) > $len) {
        $txt = mb_substr($txt, 0, $len) . '…';
    } elseif (strlen($txt) > $len) {
        $txt = substr($txt, 0, $len) . '…';
    }
    return htmlspecialchars($txt);
}

function service_tag(array $p): string {
    if (($p['service'] ?? '') === 'bulkpublish') {
        return '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25">BP</span>';
    }
    return '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-violet-500/15 text-violet-300 border border-violet-500/25">ZN</span>';
}

function platforms_chips(array $p): string {
    $chips = [];
    foreach (($p['platforms'] ?? []) as $pl) {
        $name = platform_meta($pl['platform'])['short'];
        $color = platform_meta($pl['platform'])['color'];
        $dark = platform_meta($pl['platform'])['dark'];
        $st = $pl['status'] ?? '';
        $dot = '';
        if ($st === 'published') $dot = ' bg-emerald-400';
        elseif ($st === 'failed') $dot = ' bg-rose-400';
        elseif ($st === 'partial') $dot = ' bg-orange-400';
        elseif ($st === 'pending') $dot = ' bg-slate-400';
        $chips[] = '<span class="inline-flex items-center gap-1.5 text-[11px] px-2 py-0.5 rounded-full border border-slate-700 text-slate-300"><span class="w-1.5 h-1.5 rounded-full ' . $dot . '"></span>' . htmlspecialchars($name) . '</span>';
    }
    return implode(' ', $chips);
}
?>
<?php if (!$hasKey): ?>
  <div class="card card-pad flex flex-col sm:flex-row items-start sm:items-center gap-4 justify-between">
    <div>
      <h2 class="text-lg font-semibold">API key required</h2>
      <p class="text-sm text-slate-400 mt-1">Configure a Zernio and/or BulkPublish API key to view your posts.</p>
    </div>
    <a href="settings.php" class="btn btn-primary shrink-0">Go to Settings</a>
  </div>
<?php else: ?>

<form method="get" id="filter-form" class="card card-pad grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
  <div>
    <label class="label">Status</label>
    <select name="status" class="select">
      <option value="">All</option>
      <?php foreach (['draft','scheduled','publishing','published','partial','failed','cancelled','processing'] as $s): ?>
        <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="label">Platform</label>
    <select name="platform" class="select">
      <option value="">All</option>
      <?php foreach (platform_list() as $p): ?>
        <option value="<?= $p ?>" <?= $filters['platform'] === $p ? 'selected' : '' ?>><?= htmlspecialchars(platform_meta($p)['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="label">Search</label>
    <input type="text" name="search" class="input" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Content text…">
  </div>
  <div>
    <label class="label">From</label>
    <input type="date" name="dateFrom" class="input" value="<?= htmlspecialchars($filters['dateFrom']) ?>">
  </div>
  <div>
    <label class="label">To</label>
    <input type="date" name="dateTo" class="input" value="<?= htmlspecialchars($filters['dateTo']) ?>">
  </div>
  <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="posts.php" class="btn btn-ghost btn-sm">Reset</a>
    <a href="composer.php" class="btn btn-primary btn-sm ml-auto">+ New post</a>
  </div>
</form>

<?php if ($error): ?>
  <div class="px-4 py-3 rounded-xl text-sm text-rose-200 bg-rose-500/10 border border-rose-500/30"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card overflow-hidden">
  <?php if (!$posts): ?>
    <div class="p-10 text-center text-sm text-slate-400">
      No posts found<?= $error ? ' (check your API keys / filters)' : '.' ?>
    </div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Content</th>
          <th>Status</th>
          <th>Channels</th>
          <th>Scheduled</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
          <tr>
            <td class="max-w-xs">
              <div class="flex items-center gap-2">
                <?= service_tag($p) ?>
                <div class="truncate"><?= post_preview($p) ?></div>
              </div>
              <div class="text-[11px] text-slate-600 mt-0.5 font-mono"><?= htmlspecialchars($p['_id']) ?></div>
            </td>
            <td><?= status_badge($p['status'] ?? '') ?></td>
            <td><?= platforms_chips($p) ?></td>
            <td class="text-sm text-slate-400 whitespace-nowrap">
              <?= !empty($p['scheduledFor']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($p['scheduledFor']))) : ($p['status'] === 'published' ? '<span class="text-emerald-400">published</span>' : '—') ?>
            </td>
            <td class="text-right whitespace-nowrap">
              <a href="post_view.php?id=<?= htmlspecialchars($p['_id']) ?>&service=<?= htmlspecialchars($p['service']) ?>" class="btn btn-ghost btn-sm">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($pagination && $pagination['totalPages'] > 1): ?>
    <div class="flex items-center gap-2 justify-center">
      <?php $qs = http_build_query(array_merge($filters, ['limit' => $limit])); ?>
      <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="posts.php?page=<?= $page - 1 ?>&<?= htmlspecialchars($qs) ?>">Prev</a><?php endif; ?>
      <span class="text-sm text-slate-400">Page <?= $page ?> of <?= $pagination['totalPages'] ?></span>
      <?php if ($page < $pagination['totalPages']): ?><a class="btn btn-ghost btn-sm" href="posts.php?page=<?= $page + 1 ?>&<?= htmlspecialchars($qs) ?>">Next</a><?php endif; ?>
    </div>
<?php endif; ?>

<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
