<?php
require_once __DIR__ . '/includes/auth.php';

$error = '';
$setup = user_count() === 0;
$dbError = user_count() === -1;

if ($dbError):
  // Show a helpful message instead of a login form when the DB isn't ready.
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup Required · <?= htmlspecialchars(APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center px-4">
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
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $setup ? 'Setup' : 'Login' ?> · <?= htmlspecialchars(APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center px-4" style="background-image:radial-gradient(600px 300px at 20% 10%, rgba(139,92,246,.12), transparent), radial-gradient(600px 300px at 80% 90%, rgba(217,70,239,.10), transparent);">
<div class="w-full max-w-md">
  <div class="text-center mb-8">
    <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-pink-500 flex items-center justify-center text-white text-2xl font-black shadow-xl shadow-fuchsia-500/20 mb-4">P</div>
    <h1 class="text-2xl font-bold tracking-tight"><?= htmlspecialchars(APP_NAME) ?></h1>
    <p class="text-sm text-slate-500 mt-1">Bulk social publishing via the Zernio API</p>
  </div>

  <div class="card card-pad p-8">
    <?php if ($setup): ?>
      <h2 class="text-lg font-semibold mb-1">Create your admin account</h2>
      <p class="text-sm text-slate-500 mb-6">First run \u2014 set up the username and password for this site.</p>
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
</body>
</html>