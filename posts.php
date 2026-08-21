<?php
$page_title = 'Posts';
$active = 'posts';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/zernio.php';
require_once __DIR__ . '/includes/bulkpublish.php';
require_once __DIR__ . '/includes/buffer.php';

$hasZernio = (bool)get_setting('zernio_api_key', '');
$hasBp = (bool)get_setting('bulkpublish_api_key', '');
$hasBuffer = (bool)get_setting('buffer_api_key', '');
$hasKey = $hasZernio || $hasBp || $hasBuffer;
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

    if ($hasBuffer) {
        try {
            $buf = new Buffer((string)get_setting('buffer_api_key', ''));
            foreach (($buf->listProfiles()['profiles'] ?? []) as $prof) {
                $platform = buffer_map_platform((string)($prof['service'] ?? ''));
                $pname = $prof['formatted_username'] ?? ($prof['service_username'] ?? '');
                if ($filters['platform'] !== '' && $platform !== $filters['platform']) continue;
                // Buffer lists updates per profile: pull pending + sent queues.
                foreach (['pending', 'sent'] as $queue) {
                    try {
                        $updates = $queue === 'pending'
                            ? $buf->listPending((string)$prof['id'], 50)['updates']
                            : $buf->listSent((string)$prof['id'], 50)['updates'];
                        foreach ($updates as $u) {
                            $item = normalize_buffer_post($u, $platform, $pname);
                            if ($filters['search'] !== '' && stripos((string)$item['content'], $filters['search']) === false) continue;
                            $items[] = $item;
                        }
                    } catch (Throwable $e) {
                        // Skip a queue that fails; keep the rest.
                    }
                }
            }
        } catch (Throwable $e) {
            $error = trim(($error ? $error . ' | ' : '') . 'Buffer: ' . $e->getMessage());
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

function normalize_buffer_post(array $u, string $platform, string $profileName): array {
    $status = buffer_map_status((string)($u['status'] ?? ''));
    $scheduled = !empty($u['due_at'])
        ? date('Y-m-d H:i:s', (int)$u['due_at'])
        : (!empty($u['scheduled_at']) ? date('Y-m-d H:i:s', (int)$u['scheduled_at']) : null);
    $media = [];
    if (!empty($u['media']['photo'])) {
        $media[] = ['url' => (string)$u['media']['photo'], 'type' => 'image'];
    }
    return [
        '_id' => 'bf' . ($u['id'] ?? ''),
        'service' => 'buffer',
        'content' => $u['text'] ?? '',
        'status' => $status,
        'scheduledFor' => $scheduled,
        'mediaItems' => $media,
        'platforms' => [[
            'platform' => $platform,
            'status' => $status,
            'url' => '',
            'error' => '',
            'name' => $profileName,
        ]],
        '_sort' => $scheduled ?: (!empty($u['sent_at']) ? date('Y-m-d H:i:s', (int)$u['sent_at']) : ''),
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
    if (($p['service'] ?? '') === 'buffer') {
        return '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-sky-500/15 text-sky-300 border border-sky-500/25">BF</span>';
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
      <p class="text-sm text-slate-400 mt-1">Configure a Zernio, BulkPublish or Buffer key to view your posts.</p>
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
    <button type="button" id="btn-refresh-posts" class="btn btn-ghost btn-sm ml-2" title="Refresh posts">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0h15.356m-15.356-4h15.356m-15.356 4H5.582m0 0h15.356m-15.356-4h15.356M10.5 19.5L3 12l7.5-7.5M19.5 10.5l-7.5 7.5m0 0a3 3 0 11-4.243 4.243M15 15l4.243-4.243" /></svg>
      Refresh
    </button>
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
<script>
document.getElementById('btn-refresh-posts')?.addEventListener('click', async function() {
  const el = this;
  el.disabled = true;
  el.querySelector('span')?.setAttribute('textContent', 'Refreshing...');
  try {
    const res = await fetch('ajax/refresh_posts.php?' + new URLSearchParams({ page: 1, limit: 20 }), {
      method: 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (data.ok) {
      // Update the table rows - we'll replace the tbody
      const tbody = document.querySelector('#posts-table tbody');
      if (tbody) {
        let html = '';
        data.posts.forEach(function(p, i) {
          const svcTag = p.service === 'bulkpublish'
            ? '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25">BP</span>'
            : '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-violet-500/15 text-violet-300 border border-violet-500/25">ZN</span>';
          html += '<tr>';
          html += '<td class="max-w-xs"><div class="flex items-center gap-2"><span class="text-slate-500">' + svcTag + '</span><div class="truncate">' + (p.content || '') + '</div></div><div class="text-[11px] text-slate-600 font-mono">' + p._id + '</div></td>';
          html += '<td>' + (p.status ? '<span class="text-sm ' + (p.status === 'published' ? 'text-emerald-400' : '') + '">' + p.status + '</span>' : '—') + '</td>';
          html += '<td>' + (p.platforms && p.platforms.length ? p.platforms.map(function(pl) {
            const meta = platform_meta(pl.platform);
            return '<span class="inline-flex items-center gap-1.5 text-[11px] px-2 py-0.5 rounded-full border border-slate-700 text-slate-300"><span class="w-1.5 h-1.5 rounded-full ' + (pl.status === 'published' ? 'bg-emerald-400' : '') + '"></span>' + pl.platform + '</span>';
          }).join(' ') : '') + '</td>';
          html += '<td class="text-sm text-slate-400 whitespace-nowrap">' + (!p.scheduledFor ? '—' : date('Y-m-d H:i', strtotime(p.scheduledFor))) + '</td>';
          html += '<td class="text-right"><a href="post_view.php?id=' + p._id + '&service=' + p.service + '" class="btn btn-ghost btn-sm">View</a></td>';
          html += '</tr>';
        });
        tbody.innerHTML = html;
      }
      // Update pagination info
      const paginationInfo = document.querySelector('.text-sm.text-slate-400');
      if (paginationInfo) {
        paginationInfo.textContent = 'Page 1 of ' + data.totalPages + ' • ' + data.total + ' total';
      }
    }
  } catch (e) {
    toast('Failed to refresh: ' + e.message, 'error');
  } finally {
    el.disabled = false;
    el.querySelector('span')?.removeAttribute('textContent');
  }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
