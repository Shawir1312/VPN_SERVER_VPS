<?php
require_once __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Kalau sudah login, redirect ke dashboard
if (!empty($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === AUTH_USERNAME && $password === AUTH_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $username;

        // Log login ke DB
        try {
            $pdo = get_db();
            write_log($pdo, 'login', null, null, 'Login dari IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-'));
        } catch (Exception $e) { /* ignore */ }

        header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
        exit;
    } else {
        $error = 'Username atau password salah.';
        sleep(1); // brute-force throttle
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">

    <div class="login-logo">
      <div class="logo-circle">🔗</div>
      <h1><?= APP_NAME ?></h1>
      <p>Dashboard Interkoneksi WireGuard ↔ MikroTik</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <span class="alert-icon">⚠️</span>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" id="username"
               class="form-control" placeholder="Masukkan username"
               autocomplete="username" autofocus required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" id="password"
               class="form-control" placeholder="Masukkan password"
               autocomplete="current-password" required>
      </div>

      <button type="submit" class="btn btn-primary" id="loginBtn" style="width:100%; justify-content:center; padding: 10px; font-size: 14px;">
        Masuk ke Dashboard
      </button>
    </form>

    <div style="text-align:center; margin-top: 20px;">
      <small class="text-muted">
        Server: <?= htmlspecialchars(WG_SERVER_ENDPOINT) ?>
      </small>
    </div>

  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', () => {
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Memverifikasi...';
});
</script>
</body>
</html>
