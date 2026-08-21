<?php
$page_title = 'Settings';
$active = 'settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/zernio.php';
require_once __DIR__ . '/includes/bulkpublish.php';

$zernioKey = (string)get_setting('zernio_api_key', '');
$bpKey = (string)get_setting('bulkpublish_api_key', '');
$telegramKey = (string)get_setting('telegram_bot_token', '');
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    if ($_POST['action'] === 'save_key') {
        $newKey = trim($_POST['api_key'] ?? '');
        if ($newKey === '') {
            $message = 'API key cannot be empty.';
            $messageType = 'error';
        } else {
            set_setting('zernio_api_key', $newKey);
            $zernioKey = $newKey;
            $message = 'Zernio API key saved.';
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'clear_key') {
        set_setting('zernio_api_key', '');
        $zernioKey = '';
        $message = 'Zernio API key removed.';
        $messageType = 'success';
    } elseif ($_POST['action'] === 'save_bp_key') {
        $newKey = trim($_POST['bp_api_key'] ?? '');
        if ($newKey === '') {
            $message = 'BulkPublish API key cannot be empty.';
            $messageType = 'error';
        } else {
            set_setting('bulkpublish_api_key', $newKey);
            $bpKey = $newKey;
            $message = 'BulkPublish API key saved.';
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'clear_bp_key') {
        set_setting('bulkpublish_api_key', '');
        $bpKey = '';
        $message = 'BulkPublish API key removed.';
        $messageType = 'success';
    }
}

// Test connections (non-fatal).
$zernioTest = null;
if ($zernioKey !== '') {
    try {
        $client = new Zernio($zernioKey);
        $accounts = $client->listAccounts('', 'connected');
        $profiles = $client->listProfiles();
        $zernioTest = [
            'ok' => true,
            'accounts' => count($accounts['accounts'] ?? []),
            'profiles' => count($profiles['profiles'] ?? []),
        ];
    } catch (Throwable $e) {
        $zernioTest = ['ok' => false, 'error' => $e->getMessage()];
    }
}

$bpTest = null;
if ($bpKey !== '') {
    try {
        $bp = new BulkPublish($bpKey);
        $channels = $bp->listChannels();
        $platforms = $bp->listPlatforms();
        $bpTest = [
            'ok' => true,
            'channels' => count($channels['channels'] ?? []),
            'platforms' => count($platforms['platforms'] ?? []),
        ];
    } catch (Throwable $e) {
        $bpTest = ['ok' => false, 'error' => $e->getMessage()];
    }
}
?>
<div class="max-w-2xl">

  <?php if ($message): ?>
    <div class="mb-6 px-4 py-3 rounded-lg text-sm <?= $messageType === 'success' ? 'text-emerald-200 bg-emerald-500/10 border border-emerald-500/30' : 'text-rose-200 bg-rose-500/10 border border-rose-500/30' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <!-- Zernio -->
  <div class="card card-pad mb-6">
    <div class="flex items-center justify-between mb-1">
      <h2 class="text-base font-semibold">Zernio API key</h2>
      <?php if ($zernioTest && $zernioTest['ok']): ?>
        <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-500/25">connected</span>
      <?php elseif ($zernioKey): ?>
        <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-300 border border-rose-500/25">error</span>
      <?php endif; ?>
    </div>
    <p class="text-sm text-slate-500 mb-5">
      Used to detect your connected social channels and create posts. Get it from
      <a href="https://zernio.com" target="_blank" rel="noopener" class="text-violet-400 hover:text-violet-300">zernio.com</a> → Settings → API Keys.
      The key is stored in your database and never exposed to the browser.
    </p>

    <form method="post" class="space-y-4">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div>
        <label class="label">API key <?= $zernioKey ? '<span class="text-emerald-400 normal-case">(saved)</span>' : '' ?></label>
        <input type="password" name="api_key" class="input font-mono" placeholder="sk_..." value="">
        <p class="text-xs text-slate-600 mt-2">
          <?= $zernioKey ? 'A key is currently saved (hidden). Paste a new one to replace it, or leave blank and use Remove.' : 'No key saved yet.' ?>
        </p>
      </div>
      <div class="flex gap-3">
        <button type="submit" name="action" value="save_key" class="btn btn-primary">Save key</button>
        <?php if ($zernioKey): ?>
          <button type="submit" name="action" value="clear_key" class="btn btn-ghost" onclick="return confirm('Remove the saved Zernio API key?')">Remove</button>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($zernioKey && $zernioTest): ?>
      <div class="mt-4">
        <?php if ($zernioTest['ok']): ?>
          <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/25">
            <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
            <div class="text-sm">
              <strong class="text-emerald-300">Connected</strong>
              <span class="text-slate-400"> · <?= $zernioTest['accounts'] ?> connected accounts · <?= $zernioTest['profiles'] ?> profiles</span>
            </div>
          </div>
        <?php else: ?>
          <div class="px-4 py-3 rounded-xl text-sm text-rose-200 bg-rose-500/10 border border-rose-500/30">
            <strong>Connection failed:</strong> <?= htmlspecialchars($zernioTest['error']) ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- BulkPublish -->
  <div class="card card-pad mb-6">
    <div class="flex items-center justify-between mb-1">
      <h2 class="text-base font-semibold">BulkPublish API key</h2>
      <?php if ($bpTest && $bpTest['ok']): ?>
        <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-500/25">connected</span>
      <?php elseif ($bpKey): ?>
        <span class="text-[11px] px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-300 border border-rose-500/25">error</span>
      <?php endif; ?>
    </div>
    <p class="text-sm text-slate-500 mb-5">
      Second posting service. Used to detect connected channels and create posts. Get your key from
      <a href="https://app.bulkpublish.com" target="_blank" rel="noopener" class="text-violet-400 hover:text-violet-300">app.bulkpublish.com</a> → Settings → API Keys.
    </p>

    <form method="post" class="space-y-4">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div>
        <label class="label">API key <?= $bpKey ? '<span class="text-emerald-400 normal-case">(saved)</span>' : '' ?></label>
        <input type="password" name="bp_api_key" class="input font-mono" placeholder="bp_..." value="">
        <p class="text-xs text-slate-600 mt-2">
          <?= $bpKey ? 'A key is currently saved (hidden). Paste a new one to replace it, or leave blank and use Remove.' : 'No key saved yet.' ?>
        </p>
      </div>
      <div class="flex gap-3">
        <button type="submit" name="action" value="save_bp_key" class="btn btn-primary">Save key</button>
        <?php if ($bpKey): ?>
          <button type="submit" name="action" value="clear_bp_key" class="btn btn-ghost" onclick="return confirm('Remove the saved BulkPublish API key?')">Remove</button>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($bpKey && $bpTest): ?>
      <div class="mt-4">
        <?php if ($bpTest['ok']): ?>
          <div class="flex items-center gap-3 p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/25">
            <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
            <div class="text-sm">
              <strong class="text-emerald-300">Connected</strong>
              <span class="text-slate-400"> · <?= $bpTest['channels'] ?> channels · <?= $bpTest['platforms'] ?> platforms</span>
            </div>
          </div>
        <?php else: ?>
          <div class="px-4 py-3 rounded-xl text-sm text-rose-200 bg-rose-500/10 border border-rose-500/30">
            <strong>Connection failed:</strong> <?= htmlspecialchars($bpTest['error']) ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Telegram Bot -->
  <div class="card card-pad mb-6">
    <h2 class="text-base font-semibold mb-3">Telegram Bot</h2>
    <p class="text-sm text-slate-500 mb-4">
      Telegram bot settings have moved to a <a href="telegram.php" class="text-violet-400 hover:text-violet-300 font-medium">dedicated page</a>.
    </p>
  </div>

  <div class="card card-pad">
    <h2 class="text-base font-semibold mb-3">Installation notes</h2>
    <ul class="text-sm text-slate-400 space-y-1.5 list-disc list-inside">
      <li>Database tables are created by importing <code class="text-violet-300">install.sql</code> in phpMyAdmin.</li>
      <li>Edit <code class="text-violet-300">config.php</code> if your database host / credentials differ.</li>
      <li>Zernio media uploads go straight to Zernio's storage (presigned URLs). BulkPublish media is uploaded through this server to <code class="text-violet-300">POST /api/media</code>.</li>
      <li>When channels from both services are selected in one post, two posts are created (one per service).</li>
    </ul>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
