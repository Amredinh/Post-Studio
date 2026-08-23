<?php
$page_title = 'Telegram Bot';
$active = 'telegram';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/telegram.php';

$telegramKey = (string)get_setting('telegram_bot_token', '');
$message = '';
$messageType = '';

$botUsername = '';
$botName = '';
$botError = '';
$webhookUrl = '';
$webhookPending = 0;
$webhookError = '';
$webhookSet = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    $action = $_POST['action'];

    if ($action === 'save_telegram_key') {
        $newKey = trim($_POST['bot_token'] ?? '');
        if ($newKey === '') {
            $message = 'Telegram bot token cannot be empty.';
            $messageType = 'error';
        } else {
            try {
                $test = new Telegram($newKey);
                $me = $test->getMe();
                set_setting('telegram_bot_token', $newKey);
                $telegramKey = $newKey;
                $message = 'Token verified and saved — connected as @' . ($me['username'] ?? '?') . '.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = 'Telegram rejected this token: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'clear_telegram_key') {
        set_setting('telegram_bot_token', '');
        $telegramKey = '';
        $message = 'Telegram bot token removed.';
        $messageType = 'success';
    } elseif ($action === 'set_webhook' && $telegramKey) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443 ? 'https://' : 'http://';
        $dir = dirname($_SERVER['REQUEST_URI']);
        if ($dir === '/' || $dir === '\\') $dir = '';
        $target = rtrim($protocol . $_SERVER['HTTP_HOST'] . $dir, '/') . '/ajax/telegram_bot.php';
        try {
            (new Telegram($telegramKey))->setWebhook($target);
            $message = 'Webhook registered: ' . $target;
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'Failed to set webhook: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'remove_webhook' && $telegramKey) {
        try {
            (new Telegram($telegramKey))->deleteWebhook();
            $message = 'Webhook removed.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'Failed to remove webhook: ' . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'gen_pair_code') {
        telegram_new_pair_code();
        $message = 'Fresh pairing code generated (valid 15 minutes).';
        $messageType = 'success';
    } elseif ($action === 'revoke_chat') {
        telegram_revoke_chat((string)($_POST['chat_id'] ?? ''));
        $message = 'Chat revoked — it can no longer use the bot.';
        $messageType = 'success';
    }
}

/* Live status lookups (only when a token exists). */
if ($telegramKey !== '') {
    try {
        $tg = new Telegram($telegramKey);
        $me = $tg->getMe();
        $botUsername = (string)($me['username'] ?? '');
        $botName = (string)($me['first_name'] ?? $botUsername);
    } catch (Throwable $e) {
        $botError = $e->getMessage();
    }
    try {
        $wh = (new Telegram($telegramKey))->getWebhookInfo();
        $webhookUrl = (string)($wh['url'] ?? '');
        $webhookPending = (int)($wh['pending_update_count'] ?? 0);
        $webhookError = (string)($wh['last_error_message'] ?? '');
        $webhookSet = $webhookUrl !== '';
    } catch (Throwable $e) { /* ignore */ }
}

$allowedChats = telegram_allowed_chats();
$pairCode = (string)get_setting('telegram_pair_code', '');
$pairExpires = (string)get_setting('telegram_pair_expires', '');
$pairValid = $pairCode !== '' && $pairExpires !== '' && strtotime($pairExpires) > time();
?>
<div class="max-w-5xl mx-auto space-y-6">
  <?php if ($message): ?>
    <div class="px-4 py-3 rounded-xl text-sm <?= $messageType === 'success' ? 'text-emerald-200 bg-emerald-500/10 border border-emerald-500/30' : 'text-rose-200 bg-rose-500/10 border border-rose-500/30' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <!-- ============ Status hero ============ -->
  <div class="card card-pad">
    <div class="flex flex-col md:flex-row md:items-center gap-5">
      <div class="w-14 h-14 rounded-2xl bg-sky-500/15 flex items-center justify-center shrink-0">
        <svg class="w-8 h-8 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12zm0 0h7.5"/></svg>
      </div>
      <div class="flex-1 min-w-0">
        <?php if ($botUsername): ?>
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="text-lg font-bold"><?= htmlspecialchars($botName) ?></h1>
            <span class="text-sm text-emerald-300 font-mono">@<?= htmlspecialchars($botUsername) ?></span>
            <span class="rounded-full py-0.5 px-2.5 bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-[11px] font-medium">Connected</span>
          </div>
          <p class="text-xs text-slate-400 mt-1">
            Webhook:
            <?= $webhookSet
              ? '<span class="text-emerald-400">active</span> · ' . $webhookPending . ' pending update' . ($webhookPending === 1 ? '' : 's')
              : '<span class="text-rose-400">not configured</span>' ?>
            <?php if ($webhookError): ?> · <span class="text-amber-400">last error: <?= htmlspecialchars($webhookError) ?></span><?php endif; ?>
          </p>
        <?php else: ?>
          <h1 class="text-lg font-bold">Telegram Bot</h1>
          <p class="text-sm <?= $botError ? 'text-rose-300' : 'text-slate-400' ?> mt-1">
            <?= $telegramKey && $botError
              ? '⚠️ Saved token was rejected by Telegram: ' . htmlspecialchars($botError)
              : 'Not connected yet. Save your bot token below to bring the bot online.' ?>
          </p>
        <?php endif; ?>
      </div>
      <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="btn btn-ghost self-start">Create bot via @BotFather</a>
    </div>

    <!-- token row -->
    <form method="post" class="mt-5 flex flex-col sm:flex-row gap-3 sm:items-end">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="save_telegram_key">
      <div class="flex-1">
        <label class="label mb-1.5">Bot token</label>
        <input type="password" name="bot_token" class="input font-mono" placeholder="<?= $telegramKey ? '••••••• saved — paste a new one to replace' : '123456789:ABCdefGhIklMNOpQRSTUvwxYZ' ?>" value="">
      </div>
      <button type="submit" class="btn btn-primary">Save &amp; verify</button>
      <?php if ($telegramKey): ?>
        <button type="submit" name="action" value="clear_telegram_key" class="btn btn-ghost text-rose-400 hover:text-rose-300"
                onclick="return confirm('Remove the Telegram bot token? The bot goes offline immediately.')">Remove</button>
      <?php endif; ?>
    </form>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- ============ Webhook ============ -->
    <div class="card card-pad flex flex-col">
      <h2 class="text-base font-bold mb-1">Webhook registration</h2>
      <p class="text-sm text-slate-400 mb-4 flex-1">Telegram needs a public HTTPS URL to deliver messages instantly.</p>

      <div class="mb-4 p-3 rounded-xl border <?= $webhookSet ? 'bg-emerald-500/5 border-emerald-500/25' : 'bg-slate-800/50 border-slate-700/50' ?>">
        <div class="flex items-center gap-2.5">
          <span class="w-2.5 h-2.5 rounded-full shrink-0 <?= $webhookSet ? 'bg-emerald-400' : 'bg-slate-500' ?>"></span>
          <strong class="text-sm <?= $webhookSet ? 'text-emerald-300' : 'text-slate-300' ?>"><?= $webhookSet ? 'Active' : 'Not set' ?></strong>
        </div>
        <?php if ($webhookUrl): ?>
          <p class="text-[11px] text-slate-400 break-all mt-2 bg-slate-900 p-2 rounded-lg font-mono border border-slate-800"><?= htmlspecialchars($webhookUrl) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($telegramKey): ?>
        <form method="post" class="flex items-center gap-3">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button type="submit" name="action" value="set_webhook" class="btn <?= $webhookSet ? 'btn-ghost' : 'btn-primary' ?>">
            <?= $webhookSet ? 'Re-register webhook' : 'Register webhook' ?>
          </button>
          <?php if ($webhookSet): ?>
            <button type="submit" name="action" value="remove_webhook" class="btn btn-ghost text-rose-400 hover:text-rose-300">Remove</button>
          <?php endif; ?>
        </form>
      <?php else: ?>
        <div class="p-5 text-center border border-dashed border-slate-800 rounded-xl bg-slate-900/30 text-slate-500 text-sm">
          Save a bot token first.
        </div>
      <?php endif; ?>
    </div>

    <!-- ============ Access control ============ -->
    <div class="card card-pad flex flex-col">
      <h2 class="text-base font-bold mb-1 flex items-center gap-2">
        🔐 Access control
        <span class="rounded-full py-0.5 px-2 text-[10px] <?= $allowedChats ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-300' : 'bg-amber-500/10 border border-amber-500/20 text-amber-300' ?>">
          <?= count($allowedChats) ?> paired chat<?= count($allowedChats) === 1 ? '' : 's' ?>
        </span>
      </h2>
      <p class="text-sm text-slate-400 mb-4 flex-1">
        The bot is <b class="text-slate-200">private</b>. Only paired chats can post through it — anyone else gets locked out.
      </p>

      <!-- pairing code -->
      <div class="mb-4 p-4 rounded-xl border border-violet-500/25 bg-violet-500/5">
        <p class="text-xs text-slate-400 mb-2">Pairing code — send this to the bot as <code class="text-violet-300">/pair &lt;code&gt;</code>:</p>
        <?php if ($pairValid): ?>
          <div class="flex items-center gap-3 flex-wrap">
            <span class="font-mono text-2xl font-bold tracking-[0.35em] text-violet-200"><?= htmlspecialchars($pairCode) ?></span>
            <span class="text-[11px] text-slate-400">expires <?= date('H:i', strtotime($pairExpires)) ?></span>
          </div>
        <?php else: ?>
          <span class="text-sm text-slate-500">No active code</span>
        <?php endif; ?>
        <form method="post" class="mt-3">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button type="submit" name="action" value="gen_pair_code" class="btn btn-ghost border-violet-500/30 text-xs">
            <?= $pairValid ? 'Generate fresh code' : 'Generate pairing code' ?>
          </button>
        </form>
      </div>

      <!-- paired chats -->
      <?php if ($allowedChats): ?>
        <div class="space-y-2">
          <?php foreach ($allowedChats as $c): ?>
            <div class="flex items-center justify-between gap-3 p-2.5 rounded-lg border border-slate-800 bg-slate-900/50">
              <div class="min-w-0">
                <p class="text-sm text-slate-200 truncate"><?= htmlspecialchars($c['title'] ?: ('Chat ' . $c['id'])) ?>
                  <?php if (!empty($c['username'])): ?><span class="text-xs text-slate-500">@<?= htmlspecialchars($c['username']) ?></span><?php endif; ?>
                </p>
                <p class="text-[11px] text-slate-500">ID <span class="font-mono"><?= htmlspecialchars((string)$c['id']) ?></span> · added <?= htmlspecialchars(substr((string)($c['added_at'] ?? ''), 0, 16)) ?></p>
              </div>
              <form method="post" class="shrink-0">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="revoke_chat">
                <input type="hidden" name="chat_id" value="<?= htmlspecialchars((string)$c['id']) ?>">
                <button type="submit" class="btn btn-ghost text-rose-400 hover:text-rose-300 text-xs"
                        onclick="return confirm('Revoke this chat? It loses all bot access.')">Revoke</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="p-4 rounded-xl border border-dashed border-slate-800 bg-slate-900/30 text-center text-sm text-slate-500">
          No chats paired yet — generate a code above and send <code>/pair &lt;code&gt;</code> to your bot.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============ How the bot works ============ -->
  <div class="card card-pad">
    <h3 class="text-base font-semibold mb-1">How the bot works</h3>
    <p class="text-sm text-slate-400 mb-5">Everything runs through one guided wizard — caption, channels, schedule, confirm.</p>

    <!-- wizard steps -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
      <?php
      $steps = [
        ['📝', 'Content', 'Send caption text and/or drop in a photo or video — any order works.'],
        ['📣', 'Channels', 'Tap channels from all services to toggle them ✅. Select-all supported.'],
        ['🕒', 'Schedule', 'Publish immediately or pick a UTC date &amp; time.'],
        ['🚀', 'Confirm', 'Review the summary card, then publish. Per-service results reported.'],
      ];
      foreach ($steps as $i => $s): ?>
        <div class="p-4 rounded-xl border border-slate-800 bg-slate-900/50">
          <div class="flex items-center gap-2 mb-1.5">
            <span class="w-6 h-6 rounded-full bg-violet-500/15 text-violet-300 text-xs font-bold flex items-center justify-center"><?= $i + 1 ?></span>
            <span><?= $s[0] ?></span>
            <b class="text-sm text-slate-200"><?= $s[1] ?></b>
          </div>
          <p class="text-xs text-slate-400 leading-relaxed"><?= $s[2] ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- command reference -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <?php
      $cmds = [
        ['/start',  'Main menu',       'Shows the action keyboard: new post, analytics, recent, channels.', 'violet'],
        ['/post',   'Post wizard',     'Jumps straight into step 1 of the composer.',                       'fuchsia'],
        ['/analytics','Analytics',     'Real published / scheduled / failed counts plus reach when available.', 'sky'],
        ['/recent', 'Recent posts',    'Last posts across Zernio & BulkPublish with live status icons.',    'emerald'],
        ['/channels','Channel list',   'Browse every connected channel grouped by service.',                'amber'],
        ['/cancel', 'Discard draft',   'Aborts whatever you were composing. Works at any step.',            'rose'],
      ];
      foreach ($cmds as $c): ?>
        <div class="p-4 rounded-xl border border-slate-800 bg-slate-900/50 flex gap-3.5">
          <div class="px-2 py-1 rounded-lg bg-<?= $c[3] ?>-500/10 text-<?= $c[3] ?>-400 font-mono text-xs h-fit shrink-0"><?= $c[0] ?></div>
          <div class="min-w-0">
            <h4 class="font-medium text-sm text-slate-200"><?= $c[1] ?></h4>
            <p class="text-xs text-slate-400 mt-0.5"><?= $c[2] ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
