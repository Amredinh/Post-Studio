<?php
$page_title = 'Telegram Bot';
$active = 'telegram';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/telegram.php';

$telegramKey = (string)get_setting('telegram_bot_token', '');
$message = '';
$messageType = '';
$webhookResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    if ($_POST['action'] === 'save_telegram_key') {
        $newKey = trim($_POST['bot_token'] ?? '');
        if ($newKey === '') {
            $message = 'Telegram bot token cannot be empty.';
            $messageType = 'error';
        } else {
            set_setting('telegram_bot_token', $newKey);
            $telegramKey = $newKey;
            $message = 'Telegram bot token saved.';
            $messageType = 'success';
        }
    } elseif ($_POST['action'] === 'clear_telegram_key') {
        set_setting('telegram_bot_token', '');
        $telegramKey = '';
        $message = 'Telegram bot token removed.';
        $messageType = 'success';
    } elseif ($_POST['action'] === 'set_webhook') {
        if ($telegramKey) {
            $tg = new Telegram($telegramKey);
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domainName = $_SERVER['HTTP_HOST'];
            $dir = dirname($_SERVER['REQUEST_URI']);
            if ($dir === '/' || $dir === '\\') $dir = '';
            $webhookUrl = rtrim($protocol . $domainName . $dir, '/') . '/ajax/telegram_bot.php';
            try {
                $webhookResult = $tg->setWebhook($webhookUrl);
                $message = 'Webhook successfully set to: ' . $webhookUrl;
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = 'Failed to set webhook: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'remove_webhook') {
        if ($telegramKey) {
            $tg = new Telegram($telegramKey);
            try {
                $webhookResult = $tg->deleteWebhook();
                $message = 'Webhook removed successfully.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $message = 'Failed to remove webhook: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}
?>

<div class="max-w-4xl mx-auto space-y-6">
  <?php if ($message): ?>
    <div class="px-4 py-3 rounded-xl text-sm <?= $messageType === 'success' ? 'text-emerald-200 bg-emerald-500/10 border border-emerald-500/30' : 'text-rose-200 bg-rose-500/10 border border-rose-500/30' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    <!-- Token Setup -->
    <div class="card card-pad flex flex-col">
      <h2 class="text-lg font-bold mb-2 flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12zm0 0h7.5"/></svg>
        Telegram Configuration
      </h2>
      <p class="text-sm text-slate-400 mb-6 flex-1">
        Connect a Telegram bot to enable posting and tracking content directly from Telegram. 
        Create a new bot via <a href="https://t.me/BotFather" target="_blank" rel="noopener" class="text-violet-400 hover:text-violet-300">@BotFather</a> to retrieve your token.
      </p>

      <form method="post" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save_telegram_key">
        
        <div>
          <label class="label mb-2">Bot Token <?= $telegramKey ? '<span class="text-emerald-400 normal-case ml-2 rounded-full py-0.5 px-2 bg-emerald-500/10 border border-emerald-500/20 text-[10px]">Saved</span>' : '<span class="text-rose-400 normal-case ml-2 rounded-full py-0.5 px-2 bg-rose-500/10 border border-rose-500/20 text-[10px]">Not Configured</span>' ?></label>
          <input type="password" name="bot_token" class="input font-mono" placeholder="123456789:ABCdefGhIklMNOpQRSTUvwxYZ" value="">
          <p class="text-xs text-slate-500 mt-2">
            <?= $telegramKey ? 'A token is currently saved (hidden). Paste a new one to replace it.' : 'Enter your bot token above.' ?>
          </p>
        </div>
        
        <div class="flex items-center gap-3 pt-2">
          <button type="submit" class="btn btn-primary">Save token</button>
          <?php if ($telegramKey): ?>
            <button type="submit" name="action" value="clear_telegram_key" class="btn btn-ghost" onclick="return confirm('Remove the saved Telegram bot token? You will lose bot functionality.')">Remove</button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Webhook Setup -->
    <div class="card card-pad flex flex-col">
      <h2 class="text-lg font-bold mb-2 flex items-center gap-2">
        <svg class="w-5 h-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
        Webhook Registration
      </h2>
      <p class="text-sm text-slate-400 mb-4 flex-1">
        Telegram needs a public HTTPS URL (webhook) to send messages to your server instantly. You must register this webhook to receive bot interactions.
      </p>

      <?php if ($telegramKey): 
        try {
            $tg = new Telegram($telegramKey);
            $webhook = $tg->getWebhookInfo();
            $url = $webhook['url'] ?? '';
            $statusOk = !empty($url);
        } catch (Throwable $e) {
            $statusOk = false;
            $url = 'Error fetching webhook info';
        }
      ?>
        <div class="mb-5 p-4 rounded-xl <?= $statusOk ? 'bg-emerald-500/5 border border-emerald-500/25' : 'bg-slate-800/50 border border-slate-700/50' ?>">
          <div class="flex items-center gap-3 mb-2">
            <span class="w-3 h-3 rounded-full <?= $statusOk ? 'bg-emerald-400' : 'bg-slate-500' ?>"></span>
            <strong class="text-sm <?= $statusOk ? 'text-emerald-300' : 'text-slate-300' ?>"><?= $statusOk ? 'Active' : 'Not configured' ?></strong>
          </div>
          <?php if ($url): ?>
            <p class="text-xs text-slate-400 break-all bg-slate-900 p-2 rounded-lg font-mono border border-slate-800"><?= htmlspecialchars($url) ?></p>
          <?php endif; ?>
        </div>
        
        <form method="post" class="flex items-center gap-3">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <button type="submit" name="action" value="set_webhook" class="btn <?= $statusOk ? 'btn-ghost border-violet-500/30' : 'btn-primary' ?>">
            <?= $statusOk ? 'Refresh Webhook' : 'Register Webhook' ?>
          </button>
          <?php if ($statusOk): ?>
            <button type="submit" name="action" value="remove_webhook" class="btn btn-ghost text-rose-400 hover:text-rose-300">Remove</button>
          <?php endif; ?>
        </form>
      <?php else: ?>
        <div class="p-6 text-center border border-dashed border-slate-800 rounded-2xl bg-slate-900/30 text-slate-500 text-sm">
          Save a bot token first to configure your webhook.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($telegramKey): ?>
  <!-- Bot Instructions & Commands -->
  <div class="card card-pad">
    <h3 class="text-lg font-semibold mb-4 text-slate-100">Bot Interactive Commands</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="p-4 rounded-xl border border-slate-800 bg-slate-900/50 flex gap-4">
        <div class="w-10 h-10 rounded-full bg-violet-500/10 flex items-center justify-center shrink-0 text-violet-400 font-mono text-xs">/start</div>
        <div>
          <h4 class="font-medium text-sm text-slate-200">Start Registration</h4>
          <p class="text-xs text-slate-400 mt-1">Initiates bot interaction and shows the main menu.</p>
        </div>
      </div>
      
      <div class="p-4 rounded-xl border border-slate-800 bg-slate-900/50 flex gap-4">
        <div class="w-10 h-10 rounded-full bg-fuchsia-500/10 flex items-center justify-center shrink-0 text-fuchsia-400 font-mono text-xs">/post</div>
        <div>
          <h4 class="font-medium text-sm text-slate-200">Create a Post</h4>
          <p class="text-xs text-slate-400 mt-1">Starts an interactive flow. You will be asked to select networks, enter text, and attach media.</p>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-slate-800 bg-slate-900/50 flex gap-4">
        <div class="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center shrink-0 text-emerald-400 font-mono text-xs">/status</div>
        <div>
          <h4 class="font-medium text-sm text-slate-200">Check Post Status</h4>
          <p class="text-xs text-slate-400 mt-1">Lists the status of your 5 most recently created posts.</p>
        </div>
      </div>

      <div class="p-4 rounded-xl border border-slate-800 bg-slate-900/50 flex gap-4">
        <div class="w-10 h-10 rounded-full bg-sky-500/10 flex items-center justify-center shrink-0 text-sky-400 font-mono text-xs">/data</div>
        <div>
          <h4 class="font-medium text-sm text-slate-200">Analytics</h4>
          <p class="text-xs text-slate-400 mt-1">Get an overview of your total reach, published, and failed posts metrics.</p>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
