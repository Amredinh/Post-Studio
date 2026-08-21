<?php
/**
 * Shared page header (sidebar + top bar). Pages set $page_title and $active before include.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/platforms.php';
require_login();

$page_title = $page_title ?? APP_NAME;
$active     = $active ?? '';
$apiKeySet  = (bool)get_setting('zernio_api_key', '') || (bool)get_setting('bulkpublish_api_key', '') || (bool)get_setting('telegram_bot_token', '');
$znKeySet   = (bool)get_setting('zernio_api_key', '');
$bpKeySet   = (bool)get_setting('bulkpublish_api_key', '');
$tgKeySet   = (bool)get_setting('telegram_bot_token', '');

$nav = [
    'dashboard' => ['Dashboard', 'index.php', 'M1.5 2.25h-3A2.25 2.25 0 0 0 5.25 4.5v15a.75.75 0 0 1-.75.75h-2.25m3 0h9.75a2.25 2.25 0 0 0 2.25-2.25V4.5A2.25 2.25 0 0 0 15 2.25h-3'],
    'composer'  => ['Compose Post', 'composer.php', 'M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10'],
    'bulk'      => ['Bulk Publish', 'bulk.php', 'M3.75 3.75h13.5A1.5 1.5 0 0 1 18.75 5.25v5.25h-15V5.25A1.5 1.5 0 0 1 5.25 3.75zm0 6h15v6.75a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V9.75zM8 5.25v1.5m4-1.5v1.5'],
    'posts'     => ['Posts', 'posts.php', 'M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z'],
    'analytics' => ['Analytics', 'analytics.php', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z'],
    'telegram'  => ['Telegram Bot', 'telegram.php', 'M6 12L3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12zm0 0h7.5'],
    'settings'  => ['Settings', 'settings.php', 'M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.142-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'],
];
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> · <?= htmlspecialchars(APP_NAME) ?></title>
<meta name="csrf" content="<?= csrf_token() ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 shrink-0 bg-slate-900/80 border-r border-slate-800/80 flex flex-col sticky top-0 h-screen">
    <div class="px-5 py-5 border-b border-slate-800/80">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-pink-500 flex items-center justify-center text-white font-black shadow-lg shadow-fuchsia-500/20">P</div>
        <div>
          <div class="font-bold leading-tight tracking-tight">Post Studio</div>
          <div class="text-[11px] text-slate-500">Bulk publishing · Zernio + BulkPublish</div>
        </div>
      </div>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
      <?php foreach ($nav as $key => $item): ?>
        <?php $isActive = ($active === $key); ?>
        <a href="<?= $item[1] ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $isActive ? 'bg-violet-500/15 text-violet-300 border border-violet-500/20' : 'text-slate-400 hover:bg-slate-800/70 hover:text-slate-200 border border-transparent' ?>">
          <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item[2] ?>" />
          </svg>
          <?= $item[0] ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="px-3 py-4 border-t border-slate-800/80">
      <?php if (!$apiKeySet): ?>
        <a href="settings.php" class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-amber-300 bg-amber-500/10 border border-amber-500/20 mb-3">
          <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
          API key not configured
        </a>
      <?php endif; ?>
      <div class="flex items-center justify-between px-3">
        <span class="text-xs text-slate-500"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
        <a href="logout.php" class="text-xs font-medium text-slate-400 hover:text-rose-300 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
          Logout
        </a>
      </div>
    </div>
  </aside>

  <!-- Main -->
  <div class="flex-1 flex flex-col min-w-0">
    <header class="sticky top-0 z-20 bg-slate-950/85 backdrop-blur border-b border-slate-800/80 px-6 py-4 flex items-center justify-between">
      <h1 class="text-lg font-semibold tracking-tight"><?= htmlspecialchars($page_title) ?></h1>
      <?php if ($apiKeySet): ?>
        <span class="inline-flex items-center gap-2">
          <?php if ($znKeySet): ?>
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-violet-500/10 text-violet-300 border border-violet-500/25">
              <span class="w-2 h-2 rounded-full bg-violet-400"></span> Zernio
            </span>
          <?php endif; ?>
          <?php if ($bpKeySet): ?>
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-fuchsia-500/10 text-fuchsia-300 border border-fuchsia-500/25">
              <span class="w-2 h-2 rounded-full bg-fuchsia-400"></span> BulkPublish
            </span>
          <?php endif; ?>
          <?php if ($tgKeySet): ?>
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-300 border border-emerald-500/25">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Telegram
            </span>
          <?php endif; ?>
        </span>
      <?php endif; ?>
    </header>
    <main class="flex-1 px-6 py-6 space-y-6 max-w-[1400px] w-full mx-auto">