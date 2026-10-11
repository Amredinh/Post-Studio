<?php
/**
 * Shared page header (sidebar + top bar). Pages set $page_title and $active before include.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/platforms.php';
require_login();

$page_title = $page_title ?? APP_NAME;
$active     = $active ?? '';
$apiKeySet  = (bool)get_setting('zernio_api_key', '') || (bool)get_setting('bulkpublish_api_key', '') || (bool)get_setting('buffer_api_key', '') || (bool)get_setting('telegram_bot_token', '');
$znKeySet   = (bool)get_setting('zernio_api_key', '');
$bpKeySet   = (bool)get_setting('bulkpublish_api_key', '');
$bfKeySet   = (bool)get_setting('buffer_api_key', '');
$tgKeySet   = (bool)get_setting('telegram_bot_token', '');

$nav = [
    'dashboard' => ['Dashboard', 'index.php', 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
    'composer'  => ['Compose Post', 'composer.php', 'M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10'],
    'bulk'      => ['Bulk Publish', 'bulk.php', 'M3.75 3.75h13.5A1.5 1.5 0 0 1 18.75 5.25v5.25h-15V5.25A1.5 1.5 0 0 1 5.25 3.75zm0 6h15v6.75a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V9.75zM8 5.25v1.5m4-1.5v1.5'],
    'posts'     => ['Posts', 'posts.php', 'M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z'],
    'analytics' => ['Analytics', 'analytics.php', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z'],
    'telegram'  => ['Telegram Bot', 'telegram.php', 'M6 12L3.269 3.126A59.768 59.768 0 0 1 21.485 12 59.77 59.77 0 0 1 3.27 20.876L5.999 12zm0 0h7.5'],
    'tools'     => ['Tools & Platforms', 'tools.php', 'M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-.553-.447-1.002-1-1.002a1 1 0 0 0-1 1c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3c.186 1.613.235 3.053.256 4.585q-.001.039-.001.085a.75.75 0 0 1-1.5 0c0-1.627-.07-3.153-.26-4.702a48.337 48.337 0 0 1-4.163.3.64.64 0 0 1-.657-.643v0c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-.553-.447-1.002-1-1.002a1 1 0 0 0-1 1c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.39 48.39 0 0 1-4.163-.3'],
    'settings'  => ['Settings', 'settings.php', 'M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.142-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'],
];

// Platform colours for the JavaScript side (charts, badges).
$psPlatforms = [];
foreach (['twitter','instagram','facebook','linkedin','tiktok','youtube','pinterest','reddit','bluesky','threads','googlebusiness','telegram','snapchat','whatsapp','discord','slack'] as $pk) {
    $pm = platform_meta($pk);
    $psPlatforms[$pk] = ['name' => $pm['name'], 'short' => $pm['short'], 'color' => $pm['color']];
}
$psBottom = ['dashboard', 'composer', 'posts', 'analytics'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0a0f1f">
<title><?= htmlspecialchars($page_title) ?> · <?= htmlspecialchars(APP_NAME) ?></title>
<meta name="csrf" content="<?= csrf_token() ?>">
<script>
  /* Apply the saved (or OS) theme before first paint to avoid a flash. */
  (function () {
    var t = null;
    try { t = localStorage.getItem('ps-theme'); } catch (e) {}
    if (t !== 'light' && t !== 'dark') t = (window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark';
    var d = document.documentElement;
    d.setAttribute('data-theme', t);
    d.classList.toggle('dark', t === 'dark');
    var m = document.querySelector('meta[name="theme-color"]');
    if (m) m.setAttribute('content', t === 'dark' ? '#0a0f1f' : '#f3f5fb');
  })();
  window.PS_PLATFORMS = <?= json_encode($psPlatforms, JSON_UNESCAPED_SLASHES) ?>;
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
<div class="app-shell">

  <div class="drawer-backdrop" data-drawer-close></div>

  <!-- Sidebar (desktop) / drawer (mobile) -->
  <aside class="app-sidebar" aria-label="Main navigation">
    <div class="px-5 py-5 flex items-center justify-between" style="border-bottom:1px solid var(--border)">
      <div class="flex items-center gap-3">
        <div class="brand-mark">P</div>
        <div>
          <div class="font-bold leading-tight tracking-tight text-ink">Post Studio</div>
          <div class="text-[11px] text-faint">Zernio · BulkPublish · Buffer</div>
        </div>
      </div>
      <button type="button" class="icon-btn mobile-only" data-drawer-close aria-label="Close menu">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
      <?php foreach ($nav as $key => $item): ?>
        <a href="<?= $item[1] ?>" class="nav-link<?= $active === $key ? ' active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
          <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $item[2] ?>"/></svg>
          <?= $item[0] ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="px-3 py-4" style="border-top:1px solid var(--border)">
      <?php if (!$apiKeySet): ?>
        <a href="settings.php" class="pill pill-warn w-full justify-center mb-3" style="padding:.5rem .75rem"><span class="dot animate-pulse"></span> API key not configured</a>
      <?php endif; ?>
      <div class="flex items-center justify-between px-2">
        <span class="text-xs text-muted truncate"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
        <a href="logout.php" class="text-xs font-medium text-muted hover:text-rose-400 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
          Logout
        </a>
      </div>
    </div>
  </aside>

  <!-- Main -->
  <div class="app-main">
    <header class="app-topbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" class="icon-btn mobile-only" data-drawer-open aria-label="Open menu">
          <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
        </button>
        <h1 class="text-lg font-semibold tracking-tight truncate text-ink"><?= htmlspecialchars($page_title) ?></h1>
      </div>
      <div class="flex items-center gap-2">
        <?php if ($apiKeySet): ?>
          <span class="desktop-only inline-flex items-center gap-2">
            <?php if ($znKeySet): ?><span class="pill pill-accent"><span class="dot"></span> Zernio</span><?php endif; ?>
            <?php if ($bpKeySet): ?><span class="pill pill-accent" style="color:var(--c6);background:color-mix(in srgb,var(--c6) 12%,transparent)"><span class="dot"></span> BulkPublish</span><?php endif; ?>
            <?php if ($bfKeySet): ?><span class="pill pill-info"><span class="dot"></span> Buffer</span><?php endif; ?>
            <?php if ($tgKeySet): ?><span class="pill pill-ok"><span class="dot"></span> Telegram</span><?php endif; ?>
          </span>
        <?php endif; ?>
        <button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Toggle dark / light theme" title="Toggle theme">
          <svg class="i-moon" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
          <svg class="i-sun" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
        </button>
      </div>
    </header>
    <main class="app-content">
