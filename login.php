<?php
require_once __DIR__ . '/includes/auth.php';

$error = '';
$setup = user_count() === 0;
$dbError = user_count() === -1;

if ($dbError):
  // Show a helpful message instead of a login form when the DB isn't ready.
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Setup Required · <?= htmlspecialchars(APP_NAME) ?></title>
<script>
  (function () {
    var t = null;
    try { t = localStorage.getItem('ps-theme'); } catch (e) {}
    if (t !== 'light' && t !== 'dark') t = (window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.classList.toggle('dark', t === 'dark');
  })();
</script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css?v=2">

</head>
<body class="min-h-screen flex items-center justify-center px-4">
<div class="w-full max-w-md card card-pad p-8">
  <h1 class="text-xl font-bold mb-3">Database not connected</h1>
  <p class="text-sm text-slate-400 mb-4">
    This site could not reach its MySQL database. To finish setup:
  </p>
  <ol class="text-sm text-slate-400 list-decimal list-inside space-y-1.5 mb-5">
    <li>Import <code class="text-violet-300">install.sql</code> into the <code class="text-violet-300"><?= htmlspecialchars(DB_NAME) ?></code> database (phpMyAdmin).</li>
    <li>Verify the credentials in <code class="text-violet-300">config.php</code>.</li>
    <li>Then reload this page to create your admin account.</li>
  </ol>
  <a href="login.php" class="btn btn-primary w-full">Reload</a>
</div>
<script src="assets/js/ui.js?v=2"></script>
</body>
</html>
<?php
exit;
endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($setup) {
        $confirm = $_POST['confirm'] ?? '';
        if ($username === '' || strlen($password) < 8) {
            $error = 'Username is required and password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                $_SESSION['user_id'] = (int)db()->lastInsertId();
                $_SESSION['username'] = $username;
                header('Location: settings.php');
                exit;
            } catch (Throwable $e) {
                $error = 'Setup failed: ' . $e->getMessage();
            }
        }
    } else {
        if ($username === '' || $password === '') {
            $error = 'Enter your username and password.';
        } else {
            $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit;
            }
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= $setup ? 'Setup' : 'Login' ?> · <?= htmlspecialchars(APP_NAME) ?></title>
<script>
  (function () {
    var t = null;
    try { t = localStorage.getItem('ps-theme'); } catch (e) {}
    if (t !== 'light' && t !== 'dark') t = (window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.classList.toggle('dark', t === 'dark');
  })();
</script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css?v=2">

</head>
<body class="min-h-screen flex items-center justify-center px-4" style="background-image:radial-gradient(600px 300px at 20% 10%, var(--accent-soft), transparent), radial-gradient(600px 300px at 80% 90%, color-mix(in srgb, var(--accent-2) 12%, transparent), transparent);">
<button type="button" class="icon-btn theme-toggle" data-theme-toggle aria-label="Toggle dark / light theme" style="position:fixed;top:1rem;right:1rem">
  <svg class="i-moon" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
  <svg class="i-sun" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
</button>
<div class="w-full max-w-md">
  <div class="text-center mb-8">
    <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-pink-500 flex items-center justify-center text-white text-2xl font-black shadow-xl shadow-fuchsia-500/20 mb-4">P</div>
    <h1 class="text-2xl font-bold tracking-tight"><?= htmlspecialchars(APP_NAME) ?></h1>
    <p class="text-sm text-slate-500 mt-1">Publish to every channel from one place</p>
  </div>

  <div class="card card-pad p-8">
    <?php if ($setup): ?>
      <h2 class="text-lg font-semibold mb-1">Create your admin account</h2>
      <p class="text-sm text-slate-500 mb-6">First run — set up the username and password for this site.</p>
    <?php else: ?>
      <h2 class="text-lg font-semibold mb-6">Sign in</h2>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="mb-5 px-4 py-3 rounded-lg text-sm text-rose-200 bg-rose-500/10 border border-rose-500/30"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <div>
        <label class="label">Username</label>
        <input type="text" name="username" class="input" autocomplete="username" required autofocus>
      </div>
      <div>
        <label class="label">Password</label>
        <input type="password" name="password" class="input" autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>" required>
      </div>
      <?php if ($setup): ?>
        <div>
          <label class="label">Confirm password</label>
          <input type="password" name="confirm" class="input" autocomplete="new-password" required>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary w-full py-2.5"><?= $setup ? 'Create account' : 'Sign in' ?></button>
    </form>
  </div>

  <p class="text-center text-xs text-slate-600 mt-6">Powered by <a href="https://zernio.com" target="_blank" rel="noopener" class="text-slate-400 hover:text-violet-300">Zernio</a> API</p>
</div>
<script src="assets/js/ui.js?v=2"></script>
</body>
</html>