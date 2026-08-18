<?php
$page_title = 'Dashboard';
$active = 'dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/zernio.php';
require_once __DIR__ . '/includes/bulkpublish.php';

$zernio = zernio_client();
$bp = bulkpublish_client();
$hasKey = $zernio || $bp;
$error = null;
$accounts = [];
$zernioCount = 0;
$bpCount = 0;

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
        $error = 'Zernio: ' . $e->getMessage();
    } catch (Throwable $e) {
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
            ];
        }
        $bpCount = count($bpChannels);
    } catch (Throwable $e) {
        $error = trim(($error ? $error . ' | ' : '') . 'BulkPublish: ' . $e->getMessage());
    }
}

$byPlatform = [];
foreach ($accounts as $a) {
    $byPlatform[$a['platform']][] = $a;
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

<?php if ($hasKey && !$error): ?>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold"><?= count($accounts) ?></div>
      <div class="text-xs text-slate-500 mt-1">Connected channels</div>
    </div>
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold"><?= $platformCount ?></div>
      <div class="text-xs text-slate-500 mt-1">Platforms</div>
    </div>
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold"><?= $zernioCount ?></div>
      <div class="text-xs text-slate-500 mt-1">Zernio accounts</div>
    </div>
    <div class="card card-pad stat-tile">
      <div class="text-3xl font-bold"><?= $bpCount ?></div>
      <div class="text-xs text-slate-500 mt-1">BulkPublish channels</div>
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
      <?php foreach ($byPlatform as $platform => $list): $meta = platform_meta($platform); ?>
        <div class="card overflow-hidden">
          <div class="px-5 py-4 flex items-center justify-between border-b border-slate-800">
            <div class="flex items-center gap-3">
              <span class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-bold <?= $meta['dark'] ? 'text-slate-900' : 'text-white' ?>" style="background:<?= htmlspecialchars($meta['color']) ?>"><?= htmlspecialchars($meta['short']) ?></span>
              <div>
                <div class="font-semibold text-sm"><?= htmlspecialchars($meta['name']) ?></div>
                <div class="text-[11px] text-slate-500"><?= count($list) ?> channel<?= count($list) > 1 ? 's' : '' ?></div>
              </div>
            </div>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-500/25">connected</span>
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
                  <?php if ($a['tokenStatus'] === 'expired'): ?>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-300">expired</span>
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
