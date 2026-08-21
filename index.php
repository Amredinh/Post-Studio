<?php
$page_title = 'Dashboard';
$active = 'dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/zernio.php';
require_once __DIR__ . '/includes/bulkpublish.php';
require_once __DIR__ . '/includes/buffer.php';

$zernio = zernio_client();
$bp = bulkpublish_client();
$buf = buffer_client();
$hasKey = $zernio || $bp || $buf;
$error = null;
$accounts = [];
$zernioCount = 0;
$bpCount = 0;
$bufferCount = 0;
$zernioFailed = false;
$bpFailed = false;
$bufferFailed = false;

if ($zernio) {
    try {
        $zAccounts = $zernio->listAccounts('', 'connected')['accounts'] ?? [];
        foreach ($zAccounts as $a) {
            $a['service'] = 'zernio';
            $a['serviceLabel'] = 'Zernio';
            $a['serviceTag'] = 'ZN';
            $a['serviceClass'] = 'bg-violet-500/15 text-violet-300 border border-violet-500/25';
            $accounts[] = $a;
        }
        $zernioCount = count($zAccounts);
    } catch (ZernioException $e) {
        $zernioFailed = true;
        $error = 'Zernio: ' . $e->getMessage();
    } catch (Throwable $e) {
        $zernioFailed = true;
        $error = 'Connection failed: ' . $e->getMessage();
    }
}

if ($bp) {
    try {
        $bpChannels = $bp->listChannels()['channels'] ?? [];
        foreach ($bpChannels as $c) {
            $platform = bp_map_platform((string)($c['platform'] ?? ''));
            $accounts[] = [
                'service' => 'bulkpublish',
                'serviceLabel' => 'BulkPublish',
                'serviceTag' => 'BP',
                'serviceClass' => 'bg-fuchsia-500/15 text-fuchsia-300 border border-fuchsia-500/25',
                'platform' => $platform,
                'displayName' => $c['accountName'] ?? '',
                'username' => $c['accountName'] ?? ($c['accountId'] ?? ''),
                '_id' => 'b' . $c['id'],
                'tokenStatus' => $c['tokenStatus'] ?? null,
                'isActive' => $c['isActive'] ?? true,
            ];
        }
        $bpCount = count($bpChannels);
    } catch (Throwable $e) {
        $bpFailed = true;
        $error = trim(($error ? $error . ' | ' : '') . 'BulkPublish: ' . $e->getMessage());
    }
}

if ($buf) {
    try {
        $bufferProfiles = $buf->listProfiles()['profiles'] ?? [];
        foreach ($bufferProfiles as $p) {
            $platform = buffer_map_platform((string)($p['service'] ?? ''));
            $accounts[] = [
                'service' => 'buffer',
                'serviceLabel' => 'Buffer',
                'serviceTag' => 'BF',
                'serviceClass' => 'bg-sky-500/15 text-sky-300 border border-sky-500/25',
                'platform' => $platform,
                'displayName' => ($p['formatted_username'] ?? '') ?: ($p['display_name'] ?? ''),
                'username' => $p['formatted_username'] ?? ($p['service_username'] ?? ''),
                '_id' => 'bf' . ($p['id'] ?? ''),
            ];
        }
        $bufferCount = count($bufferProfiles);
    } catch (Throwable $e) {
        $bufferFailed = true;
        $error = trim(($error ? $error . ' | ' : '') . 'Buffer: ' . $e->getMessage());
    }
}

$byPlatform = [];
foreach ($accounts as $a) {
    $plat = $a['platform'] ?? 'other';
    $byPlatform[$plat][] = $a;
}
$platformCount = count($byPlatform);
?>

<?php if (!$hasKey): ?>
  <div class="card card-pad flex flex-col sm:flex-row items-start sm:items-center gap-4 justify-between">
    <div>
      <h2 class="text-lg font-semibold">Connect your API keys</h2>
      <p class="text-sm text-slate-400 mt-1">Add your Zernio and/or BulkPublish API keys in Settings to detect connected social accounts and start posting.</p>
    </div>
    <a href="settings.php" class="btn btn-primary shrink-0">Go to Settings</a>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="px-4 py-3 rounded-xl text-sm text-rose-200 bg-rose-500/10 border border-rose-500/30">
    <strong>API error:</strong> <?= htmlspecialchars($error) ?>
  </div>
<?php endif; ?>

<?php if ($hasKey): ?>
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold"><?= count($accounts) ?></div>
      <div class="text-xs text-slate-500 mt-1">Connected channels</div>
    </div>
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold"><?= $platformCount ?></div>
      <div class="text-xs text-slate-500 mt-1">Platforms</div>
    </div>
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold <?= $zernioFailed ? 'text-slate-600' : '' ?>"><?= $zernioCount ?></div>
      <div class="text-xs text-slate-500 mt-1">Zernio accounts<?= $zernioFailed ? ' (unavailable)' : '' ?></div>
    </div>
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold <?= $bpFailed ? 'text-slate-600' : '' ?>"><?= $bpCount ?></div>
      <div class="text-xs text-slate-500 mt-1">BulkPublish channels<?= $bpFailed ? ' (unavailable)' : '' ?></div>
    </div>
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold <?= $bufferFailed ? 'text-slate-600' : '' ?>"><?= $bufferCount ?></div>
      <div class="text-xs text-slate-500 mt-1">Buffer channels<?= $bufferFailed ? ' (unavailable)' : '' ?></div>
    </div>
  </div>

  <div class="flex items-center justify-between">
    <h2 class="text-base font-semibold">Connected channels</h2>
    <a href="composer.php" class="btn btn-primary btn-sm">+ Compose post</a>
  </div>

  <?php if (!$accounts): ?>
    <div class="p-8 text-center text-sm text-slate-400 rounded-xl border border-dashed border-slate-700">
      No connected accounts found under your API keys.
      <div class="mt-2 text-xs text-slate-500">Connect accounts in your Zernio / BulkPublish dashboard, then <a href="index.php" class="text-violet-400 hover:text-violet-300">refresh</a>.</div>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($byPlatform as $platform => $list): $meta = platform_meta($platform);
            $expCount = 0; $expSoonCount = 0; $offCount = 0;
            foreach ($list as $a) {
                $ts = $a['tokenStatus'] ?? null;
                if ($ts === 'expired') $expCount++;
                elseif ($ts === 'expiring_soon') $expSoonCount++;
                if (array_key_exists('isActive', $a) && $a['isActive'] === false) $offCount++;
            }
      ?>
        <div class="card overflow-hidden">
          <div class="px-5 py-4 flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-3">
              <span class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-bold <?= $meta['dark'] ? 'text-slate-900' : 'text-white' ?>" style="background:<?= htmlspecialchars($meta['color']) ?>"><?= htmlspecialchars($meta['short']) ?></span>
              <div>
                <div class="font-semibold text-sm"><?= htmlspecialchars($meta['name']) ?></div>
                <div class="text-[11px] text-slate-500"><?= count($list) ?> channel<?= count($list) > 1 ? 's' : '' ?></div>
              </div>
            </div>
            <?php if ($offCount === count($list)): ?>
              <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-500/10 text-slate-400 border border-slate-500/25">offline</span>
            <?php elseif ($expCount > 0 || $expSoonCount > 0): ?>
              <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-300 border border-amber-500/25">token issues</span>
            <?php else: ?>
              <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-500/25">connected</span>
            <?php endif; ?>
          </div>
          <div class="divide-y divide-slate-800/60">
            <?php foreach ($list as $a): ?>
              <div class="px-5 py-3 flex items-center justify-between">
                <div class="min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= htmlspecialchars($a['serviceClass']) ?>" title="<?= htmlspecialchars($a['serviceLabel']) ?>"><?= htmlspecialchars($a['serviceTag']) ?></span>
                    <div class="text-sm font-medium truncate"><?= htmlspecialchars($a['displayName'] ?? '') ?></div>
                  </div>
                  <div class="text-xs text-slate-500 truncate"><?= htmlspecialchars($a['username'] ?? '') ?></div>
                </div>
                <div class="flex items-center gap-2 shrink-0 ml-3">
                  <?php if (($a['tokenStatus'] ?? null) === 'expired'): ?>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-300">expired</span>
                  <?php elseif (($a['tokenStatus'] ?? null) === 'expiring_soon'): ?>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300">expiring soon</span>
                  <?php endif; ?>
                  <?php if (isset($a['isActive']) && $a['isActive'] === false): ?>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-600/20 text-slate-400">offline</span>
                  <?php endif; ?>
                  <?php if (!empty($a['profileUrl'])): ?>
                    <a href="<?= htmlspecialchars($a['profileUrl']) ?>" target="_blank" rel="noopener" class="text-slate-500 hover:text-violet-300" title="Open profile">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    </a>
                  <?php endif; ?>
                  <input type="checkbox" class="acc-copy h-4 w-4 rounded border-slate-600 text-violet-500 bg-slate-800 focus:ring-violet-500 focus:ring-offset-0" title="Copy account ID" data-id="<?= htmlspecialchars($a['_id']) ?>">
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.acc-copy').forEach(function (cb) {
      cb.addEventListener('change', function () {
        if (cb.checked) {
          navigator.clipboard.writeText(cb.dataset.id).then(function () {
            window.PostStudio && PostStudio.toast ? PostStudio.toast('Account ID copied') : alert('Copied: ' + cb.dataset.id);
            cb.checked = false;
          });
        }
      });
    });
  });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
