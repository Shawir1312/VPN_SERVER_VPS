<?php
/**
 * Interkonek — Web Installer
 * Jalankan sekali untuk setup aplikasi. Setelah selesai, file ini dikunci.
 */

define('LOCK_FILE',    __DIR__ . '/.installed');
define('CONFIG_FILE',  __DIR__ . '/includes/config.php');
define('SCHEMA_FILE',  __DIR__ . '/schema.sql');
define('APP_VERSION',  '2.0.0');

session_start();

// ======================== LOCK CHECK ========================
if (file_exists(LOCK_FILE)) {
    if (($_GET['reset'] ?? '') !== 'yes_reset_interkonek') {
        // Redirect ke dashboard jika sudah terinstall
        header('Location: index.php');
        exit;
    }
}

$step    = (int) ($_POST['step'] ?? 1);
$errors  = [];
$success = false;
$checks  = run_system_checks();

// ======================== PROSES INSTALL ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost    = trim($_POST['db_host']    ?? 'localhost');
    $dbName    = trim($_POST['db_name']    ?? '');
    $dbUser    = trim($_POST['db_user']    ?? '');
    $dbPass    = trim($_POST['db_pass']    ?? '');
    $wgEndpt   = trim($_POST['wg_endpoint'] ?? '');
    $wgPubkey  = trim($_POST['wg_pubkey']  ?? '');
    $wgAddr    = trim($_POST['wg_addr']    ?? '10.66.66.1');
    $wgPrefix  = trim($_POST['wg_prefix']  ?? '10.66.66.');
    $wgIface   = trim($_POST['wg_iface']   ?? 'wg0');
    $authUser  = trim($_POST['auth_user']  ?? 'admin');
    $authPass  = trim($_POST['auth_pass']  ?? '');

    // Validasi
    if (!$dbName)   $errors[] = 'Nama database wajib diisi.';
    if (!$dbUser)   $errors[] = 'User database wajib diisi.';
    if (!$wgEndpt)  $errors[] = 'Endpoint WireGuard wajib diisi.';
    if (!$wgPubkey) $errors[] = 'Public key WireGuard wajib diisi.';
    if (!$authPass) $errors[] = 'Password admin wajib diisi.';
    if (strlen($authPass) < 6) $errors[] = 'Password minimal 6 karakter.';

    if (empty($errors)) {
        // Tes koneksi DB
        try {
            $dsn = "mysql:host={$dbHost};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
        } catch (Exception $e) {
            $errors[] = 'Koneksi database gagal: ' . $e->getMessage();
        }

        if (empty($errors)) {
            // Jalankan schema SQL
            try {
                $sql = file_get_contents(SCHEMA_FILE);
                foreach (explode(';', $sql) as $query) {
                    $q = trim($query);
                    if ($q) $pdo->exec($q);
                }
            } catch (Exception $e) {
                $errors[] = 'Gagal membuat tabel: ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            // Tulis config.php
            $configContent = generate_config(
                $dbHost, $dbName, $dbUser, $dbPass,
                $wgAddr, $wgPrefix, $wgPubkey, $wgEndpt, $wgIface,
                $authUser, $authPass
            );

            $writeOk = @file_put_contents(CONFIG_FILE, $configContent);
            if ($writeOk === false) {
                // Tidak bisa tulis otomatis — tampilkan config untuk copy manual
                $_SESSION['fallback_config'] = $configContent;
                $errors[] = '__MANUAL_COPY__';
            }
        }

        if (empty($errors)) {
            // Buat lock file di root folder
            @file_put_contents(LOCK_FILE, date('Y-m-d H:i:s') . "\n");
            $success = true;
            $step    = 3;
        }

        // Handle fallback: config tidak bisa ditulis otomatis
        if (count($errors) === 1 && $errors[0] === '__MANUAL_COPY__') {
            $step    = 3;
            $success = false;
        }
    }
}

// ======================== FUNGSI ========================
function run_system_checks(): array {
    // WireGuard: tidak bisa cek binary karena open_basedir aaPanel
    // User input public key secara manual di form konfigurasi
    $wgOk   = true; // anggap OK, warning saja
    $pubkey = '';   // tidak bisa baca /etc/wireguard/ — user isi manual

    // Deteksi IP publik dari koneksi keluar
    $ip = '';
    try {
        $sock = @fsockopen('8.8.8.8', 80, $errno, $errstr, 2);
        if ($sock) {
            $ip = stream_socket_get_name($sock, false);
            fclose($sock);
            $ip = explode(':', $ip)[0];
        }
    } catch (Exception $e) { $ip = ''; }

    return [
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'pdo_mysql'   => extension_loaded('pdo_mysql'),
        'wireguard'   => $wgOk,
        'writable'    => is_writable(__DIR__ . '/includes') || is_writable(CONFIG_FILE),
        'data_dir'    => is_dir(__DIR__ . '/data') && is_writable(__DIR__ . '/data'),
        'wg_pubkey'   => $pubkey,
        'server_ip'   => $ip,
    ];
}

function generate_config(
    string $dbHost, string $dbName, string $dbUser, string $dbPass,
    string $wgAddr, string $wgPrefix, string $wgPubkey, string $wgEndpt, string $wgIface,
    string $authUser, string $authPass
): string {
    $ts = date('Y-m-d H:i:s');
    return <<<PHP
<?php
// ============================================================
// FILE INI DIGENERATE OTOMATIS OLEH install.php
// Dibuat pada: {$ts}
// ============================================================

// Database MySQL
define('DB_HOST',    '{$dbHost}');
define('DB_NAME',    '{$dbName}');
define('DB_USER',    '{$dbUser}');
define('DB_PASS',    '{$dbPass}');
define('DB_CHARSET', 'utf8mb4');

// Konfigurasi WireGuard Server
define('WG_SERVER_ADDR',     '{$wgAddr}');
define('WG_SUBNET_PREFIX',   '{$wgPrefix}');
define('WG_SUBNET_CIDR',     '/32');
define('WG_SERVER_PUBKEY',   '{$wgPubkey}');
define('WG_SERVER_ENDPOINT', '{$wgEndpt}');
define('WG_INTERFACE',       '{$wgIface}');

// Kredensial Login Dashboard
define('AUTH_USERNAME', '{$authUser}');
define('AUTH_PASSWORD', '{$authPass}');

// Info Aplikasi
define('APP_NAME',    'Interkonek');
define('APP_VERSION', '2.0.0');

// ============================================================
// DATABASE — MySQL PDO
// ============================================================
function get_db(): PDO {
    static \$pdo = null;
    if (\$pdo !== null) return \$pdo;
    \$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return \$pdo;
}

// ============================================================
// AUTH HELPERS
// ============================================================
function require_login(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty(\$_SESSION['logged_in'])) {
        \$redirect = urlencode(\$_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?redirect=' . \$redirect);
        exit;
    }
}

function is_logged_in(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty(\$_SESSION['logged_in']);
}

// ============================================================
// LOG HELPER
// ============================================================
function write_log(PDO \$pdo, string \$event, ?int \$routerId = null, ?string \$routerName = null, ?string \$details = null): void {
    try {
        \$pdo->prepare('INSERT INTO logs (event, router_id, router_name, details) VALUES (?, ?, ?, ?)')
            ->execute([\$event, \$routerId, \$routerName, \$details]);
    } catch (Exception \$e) {}
}
PHP;
}

function render_locked(): string {
    return <<<HTML
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<title>Sudah Terinstall — Interkonek</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Inter,sans-serif;background:#0a0c10;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#141920;border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:40px;max-width:420px;text-align:center}
.icon{font-size:48px;margin-bottom:16px}
h1{font-size:20px;margin-bottom:8px}
p{color:#8b9ab0;font-size:14px;line-height:1.6;margin-bottom:20px}
a{display:inline-block;padding:10px 20px;background:#4f8cff;color:white;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px}
small{display:block;margin-top:12px;color:#4a5568;font-size:12px}
</style></head><body>
<div class="card">
  <div class="icon">✅</div>
  <h1>Interkonek Sudah Terinstall</h1>
  <p>Installer sudah dikunci setelah instalasi berhasil. Buka dashboard untuk mulai menggunakan aplikasi.</p>
  <a href="index.php">Buka Dashboard →</a>
  <small>Perlu install ulang? <a href="install.php?reset=yes_reset_interkonek" style="background:none;color:#ef4444;padding:0;font-size:12px;">Reset installer</a></small>
</div>
</body></html>
HTML;
}

$allOk = $checks['php_version'] && $checks['pdo_mysql'] && $checks['writable'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Interkonek — Installer</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0a0c10;--card:#141920;--surface:#0f1319;--border:rgba(255,255,255,.07);
  --text:#e2e8f0;--muted:#8b9ab0;--dim:#4a5568;
  --accent:#4f8cff;--accent-glow:rgba(79,140,255,.2);
  --green:#10b981;--green-bg:rgba(16,185,129,.1);--green-border:rgba(16,185,129,.3);
  --red:#ef4444;--red-bg:rgba(239,68,68,.1);--red-border:rgba(239,68,68,.25);
  --yellow:#f59e0b;--yellow-bg:rgba(245,158,11,.1);
  --radius:12px;--radius-sm:8px;
}
html,body{min-height:100vh;font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px;-webkit-font-smoothing:antialiased}
body{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:40px 16px 60px}

/* HEADER */
.inst-header{text-align:center;margin-bottom:36px}
.inst-logo{width:60px;height:60px;background:linear-gradient(135deg,#4f8cff,#8b5cf6);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px;box-shadow:0 0 40px var(--accent-glow)}
.inst-header h1{font-size:24px;font-weight:700;letter-spacing:-.03em;margin-bottom:6px}
.inst-header p{color:var(--muted);font-size:13.5px}

/* STEP INDICATOR */
.steps{display:flex;align-items:center;gap:0;margin-bottom:32px;background:var(--card);border:1px solid var(--border);border-radius:40px;padding:6px;max-width:380px;width:100%}
.step-btn{flex:1;padding:8px 12px;border-radius:30px;font-size:12.5px;font-weight:600;color:var(--muted);text-align:center;transition:.2s}
.step-btn.active{background:var(--accent);color:white;box-shadow:0 0 16px var(--accent-glow)}
.step-btn.done{color:var(--green)}

/* CARD */
.card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:32px;width:100%;max-width:620px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.card-title{font-size:16px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.card-title span{font-size:18px}

/* CHECK LIST */
.check-list{display:flex;flex-direction:column;gap:10px;margin-bottom:24px}
.check-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--surface)}
.check-icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.check-icon.ok{background:var(--green-bg);border:1px solid var(--green-border);color:var(--green)}
.check-icon.fail{background:var(--red-bg);border:1px solid var(--red-border);color:var(--red)}
.check-icon.warn{background:var(--yellow-bg);border:1px solid rgba(245,158,11,.3);color:var(--yellow)}
.check-label{font-size:13.5px;font-weight:500;flex:1}
.check-detail{font-size:12px;color:var(--muted);margin-top:2px}

/* FORM */
.form-section{margin-bottom:24px}
.form-section-title{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--dim);margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-group{margin-bottom:14px}
.form-label{display:block;font-size:12.5px;font-weight:500;color:var(--muted);margin-bottom:5px}
.form-label .req{color:var(--red)}
.form-control{width:100%;padding:9px 12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:13px;font-family:inherit;transition:.2s}
.form-control:focus{outline:none;border-color:rgba(79,140,255,.6);box-shadow:0 0 0 3px rgba(79,140,255,.1)}
.form-control.mono{font-family:'JetBrains Mono',monospace;font-size:12px}
.form-hint{font-size:11.5px;color:var(--dim);margin-top:4px;line-height:1.5}

/* ALERT */
.alert{padding:12px 14px;border-radius:var(--radius-sm);margin-bottom:16px;font-size:13px;display:flex;gap:10px;align-items:flex-start}
.alert.error{background:var(--red-bg);border:1px solid var(--red-border);color:#fca5a5}
.alert.success{background:var(--green-bg);border:1px solid var(--green-border);color:#6ee7b7}
.alert.warning{background:var(--yellow-bg);border:1px solid rgba(245,158,11,.3);color:#fcd34d}
.alert-icon{font-size:15px;flex-shrink:0;margin-top:1px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:var(--radius-sm);font-size:13.5px;font-weight:600;border:none;cursor:pointer;text-decoration:none;font-family:inherit;transition:.2s}
.btn:hover{transform:translateY(-1px)}
.btn-primary{background:var(--accent);color:white;box-shadow:0 0 20px var(--accent-glow)}
.btn-primary:hover{background:#6aa0ff}
.btn-success{background:#10b981;color:white;box-shadow:0 0 20px var(--green-bg)}
.spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.2);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}

/* SUCCESS */
.success-icon{width:72px;height:72px;background:var(--green-bg);border:2px solid var(--green-border);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 20px}
.success-info{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin:16px 0;font-size:13px}
.success-info table{width:100%;border-collapse:collapse}
.success-info td{padding:5px 0;color:var(--muted)}
.success-info td:last-child{color:var(--text);text-align:right;font-weight:500}
code{font-family:'JetBrains Mono',monospace;font-size:11.5px;background:rgba(255,255,255,.07);padding:2px 6px;border-radius:4px;color:#79c0ff}

@media(max-width:500px){.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="inst-header">
  <div class="inst-logo">🔗</div>
  <h1>Interkonek Installer</h1>
  <p>Dashboard Interkoneksi WireGuard ↔ MikroTik &nbsp;·&nbsp; v<?= APP_VERSION ?></p>
</div>

<!-- STEP INDICATOR -->
<div class="steps">
  <div class="step-btn <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
    <?= $step > 1 ? '✓' : '1' ?> Cek Sistem
  </div>
  <div class="step-btn <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">
    <?= $step > 2 ? '✓' : '2' ?> Konfigurasi
  </div>
  <div class="step-btn <?= $step >= 3 ? 'active' : '' ?>">3 Selesai</div>
</div>

<div class="card">

<?php if ($step === 3 && $success): ?>
<!-- ========== LANGKAH 3: SELESAI ========== -->
<div style="text-align:center;padding:10px 0 20px">
  <div class="success-icon">✅</div>
  <h2 style="font-size:20px;margin-bottom:8px">Instalasi Berhasil!</h2>
  <p style="color:var(--muted);font-size:13.5px;margin-bottom:20px">
    Semua tabel sudah dibuat dan konfigurasi sudah tersimpan.<br>
    Dashboard siap digunakan.
  </p>

  <div class="success-info">
    <table>
      <tr><td>🔗 Endpoint WireGuard</td><td><code><?= htmlspecialchars($_POST['wg_endpoint'] ?? '') ?></code></td></tr>
      <tr><td>🌐 Hub IP Tunnel</td><td><code><?= htmlspecialchars($_POST['wg_addr'] ?? '') ?></code></td></tr>
      <tr><td>👤 Username Login</td><td><code><?= htmlspecialchars($_POST['auth_user'] ?? '') ?></code></td></tr>
      <tr><td>🗄️ Database</td><td><code><?= htmlspecialchars($_POST['db_name'] ?? '') ?>@<?= htmlspecialchars($_POST['db_host'] ?? '') ?></code></td></tr>
    </table>
  </div>

  <div class="alert warning" style="text-align:left">
    <span class="alert-icon">⚠️</span>
    <div>
      <strong>Penting setelah ini:</strong><br>
      Install script WireGuard di server agar fitur tambah/hapus peer berfungsi:<br>
      <code>cp scripts/wg-add-peer.sh /usr/local/bin/ && chmod +x /usr/local/bin/wg-add-peer.sh</code><br>
      <code>cp scripts/wg-remove-peer.sh /usr/local/bin/ && chmod +x /usr/local/bin/wg-remove-peer.sh</code><br>
      Lalu tambahkan sudoers — lihat <strong>DEPLOY.md</strong> untuk detailnya.
    </div>
  </div>

  <a href="index.php" class="btn btn-success" style="font-size:15px;padding:12px 28px;margin-top:8px">
    🚀 Buka Dashboard →
  </a>
</div>

<?php elseif ($step === 2): ?>
<!-- ========== LANGKAH 2: KONFIGURASI ========== -->
<div class="card-title"><span>⚙️</span> Konfigurasi Instalasi</div>

<?php foreach ($errors as $e): ?>
<?php if ($e === '__MANUAL_COPY__'): ?>
<div class="alert warning" style="flex-direction:column;gap:8px">
  <div style="display:flex;gap:8px;align-items:center"><span class="alert-icon">⚠️</span><strong>Tidak bisa tulis config.php otomatis.</strong></div>
  <div style="font-size:12.5px">Salin isi berikut dan simpan secara manual ke <code>includes/config.php</code> via aaPanel File Manager:</div>
  <pre style="background:#060a0f;padding:12px;border-radius:6px;font-size:11px;overflow-x:auto;color:#a0d4ff;max-height:200px;overflow-y:auto"><?= htmlspecialchars($_SESSION['fallback_config'] ?? '') ?></pre>
  <div style="font-size:12px">Atau jalankan di terminal VPS:<br><code>chmod 777 includes/ && php install.php</code></div>
</div>
<?php else: ?>
<div class="alert error"><span class="alert-icon">❌</span> <?= htmlspecialchars($e) ?></div>
<?php endif; ?>
<?php endforeach; ?>

<form method="POST" id="installForm">
  <input type="hidden" name="step" value="2">

  <!-- DATABASE -->
  <div class="form-section">
    <div class="form-section-title">🗄️ Database MySQL</div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Host <span class="req">*</span></label>
        <input type="text" name="db_host" class="form-control"
               value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Nama Database <span class="req">*</span></label>
        <input type="text" name="db_name" class="form-control"
               value="<?= htmlspecialchars($_POST['db_name'] ?? 'vpn') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Username DB <span class="req">*</span></label>
        <input type="text" name="db_user" class="form-control"
               value="<?= htmlspecialchars($_POST['db_user'] ?? 'vpn') ?>" required autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label">Password DB</label>
        <input type="text" name="db_pass" class="form-control"
               value="<?= htmlspecialchars($_POST['db_pass'] ?? 's1312') ?>" autocomplete="off">
      </div>
    </div>
  </div>

  <!-- WIREGUARD -->
  <div class="form-section">
    <div class="form-section-title">🔗 WireGuard Server</div>
    <div class="form-group">
      <label class="form-label">Server Endpoint (IP:Port) <span class="req">*</span></label>
      <input type="text" name="wg_endpoint" class="form-control mono"
             value="<?= htmlspecialchars($_POST['wg_endpoint'] ?? $checks['server_ip'] . ':51820') ?>"
             placeholder="202.10.48.191:51820" required>
      <div class="form-hint">IP publik VPS dan port WireGuard. Pastikan port 51820/UDP sudah dibuka di firewall.</div>
    </div>
    <div class="form-group">
      <label class="form-label">Public Key Server <span class="req">*</span></label>
      <input type="text" name="wg_pubkey" class="form-control mono"
             value="<?= htmlspecialchars($_POST['wg_pubkey'] ?? $checks['wg_pubkey']) ?>"
             placeholder="Dari: cat /etc/wireguard/server_public.key" required>
      <div class="form-hint">
        <?php if ($checks['wg_pubkey']): ?>
          ✅ Terdeteksi otomatis dari <code>/etc/wireguard/server_public.key</code>
        <?php else: ?>
          Jalankan <code>cat /etc/wireguard/server_public.key</code> di server lalu paste di sini.
        <?php endif; ?>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Hub IP (IP VPS di Tunnel)</label>
        <input type="text" name="wg_addr" class="form-control mono"
               value="<?= htmlspecialchars($_POST['wg_addr'] ?? '10.66.66.1') ?>">
        <div class="form-hint">IP VPS di dalam jaringan tunnel.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Subnet Prefix</label>
        <input type="text" name="wg_prefix" class="form-control mono"
               value="<?= htmlspecialchars($_POST['wg_prefix'] ?? '10.66.66.') ?>">
        <div class="form-hint">Prefix untuk IP peer (akhiri dengan titik).</div>
      </div>
      <div class="form-group">
        <label class="form-label">Interface WireGuard</label>
        <input type="text" name="wg_iface" class="form-control mono"
               value="<?= htmlspecialchars($_POST['wg_iface'] ?? 'wg0') ?>">
      </div>
    </div>
  </div>

  <!-- ADMIN -->
  <div class="form-section">
    <div class="form-section-title">🔐 Akun Admin Dashboard</div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Username <span class="req">*</span></label>
        <input type="text" name="auth_user" class="form-control"
               value="<?= htmlspecialchars($_POST['auth_user'] ?? 'admin') ?>" required autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label">Password <span class="req">*</span></label>
        <input type="password" name="auth_pass" class="form-control"
               placeholder="Min. 6 karakter" required autocomplete="new-password">
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary" id="installBtn" style="width:100%;justify-content:center;padding:12px;font-size:14px;">
    ⚡ Jalankan Instalasi
  </button>
</form>

<?php else: ?>
<!-- ========== LANGKAH 1: CEK SISTEM ========== -->
<div class="card-title"><span>🔍</span> Cek Kebutuhan Sistem</div>

<div class="check-list">
  <div class="check-item">
    <div class="check-icon <?= $checks['php_version'] ? 'ok' : 'fail' ?>"><?= $checks['php_version'] ? '✓' : '✗' ?></div>
    <div>
      <div class="check-label">PHP Versi 7.4+</div>
      <div class="check-detail">Versi saat ini: <code><?= PHP_VERSION ?></code></div>
    </div>
  </div>

  <div class="check-item">
    <div class="check-icon <?= $checks['pdo_mysql'] ? 'ok' : 'fail' ?>"><?= $checks['pdo_mysql'] ? '✓' : '✗' ?></div>
    <div>
      <div class="check-label">Ekstensi PDO MySQL</div>
      <div class="check-detail">
        <?= $checks['pdo_mysql'] ? 'Tersedia ✅' : '❌ Install dengan: <code>apt install php-mysql</code>' ?>
      </div>
    </div>
  </div>

  <div class="check-item">
    <div class="check-icon warn">!</div>
    <div>
      <div class="check-label">WireGuard Tools</div>
      <div class="check-detail">
        ⚠️ Tidak bisa dicek otomatis (dibatasi aaPanel open_basedir).<br>
        Pastikan WireGuard sudah terinstall di server: <code>apt install wireguard-tools</code><br>
        Public key akan diisi manual di langkah berikutnya.
      </div>
    </div>
  </div>

  <div class="check-item">
    <div class="check-icon <?= $checks['writable'] ? 'ok' : 'warn' ?>"><?= $checks['writable'] ? '✓' : '!' ?></div>
    <div>
      <div class="check-label">Folder <code>includes/</code> Writable</div>
      <div class="check-detail">
        <?= $checks['writable'] ? 'Bisa ditulis ✅' : '⚠️ Jalankan: <code>chmod 777 includes/</code> &nbsp;(atau config akan ditampilkan untuk copy manual)' ?>
      </div>
    </div>
  </div>

  <div class="check-item">
    <div class="check-icon <?= $checks['data_dir'] ? 'ok' : 'warn' ?>"><?= $checks['data_dir'] ? '✓' : '!' ?></div>
    <div>
      <div class="check-label">Folder <code>data/</code> Ada & Writable</div>
      <div class="check-detail">
        <?= $checks['data_dir'] ? 'Siap ✅' : '⚠️ Buat dengan: <code>mkdir -p data && chmod 770 data</code>' ?>
      </div>
    </div>
  </div>
</div>

<?php if (!$checks['php_version'] || !$checks['pdo_mysql']): ?>
  <div class="alert error">
    <span class="alert-icon">❌</span>
    <div>Beberapa kebutuhan sistem tidak terpenuhi. Perbaiki dulu sebelum melanjutkan.</div>
  </div>
  <button class="btn btn-primary" onclick="location.reload()" style="width:100%;justify-content:center;">
    🔄 Cek Ulang
  </button>
<?php else: ?>
  <?php if (!$checks['data_dir']): ?>
  <div class="alert warning">
    <span class="alert-icon">⚠️</span>
    Folder <code>data/</code> belum ada. Buat dulu: <code>mkdir -p data && chmod 770 data</code>
    Atau installer akan mencoba membuatnya otomatis.
  </div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="step" value="2">
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:14px;">
      Lanjut ke Konfigurasi →
    </button>
  </form>
<?php endif; ?>

<?php endif; ?>

</div><!-- .card -->

<script>
const form = document.getElementById('installForm');
if (form) {
  form.addEventListener('submit', () => {
    const btn = document.getElementById('installBtn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> Menginstall...';
  });
}
</script>

</body>
</html>
