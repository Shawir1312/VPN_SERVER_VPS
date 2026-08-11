<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['auth_username'] ?? '');
    $newPassword = trim($_POST['auth_password'] ?? '');
    $wgEndpoint  = trim($_POST['wg_endpoint'] ?? '');
    $wgPubkey    = trim($_POST['wg_pubkey'] ?? '');
    $wgAddr      = trim($_POST['wg_addr'] ?? '');
    $wgPrefix    = trim($_POST['wg_prefix'] ?? '');
    $wgIface     = trim($_POST['wg_iface'] ?? '');

    // Baca file config saat ini
    $configFile = __DIR__ . '/../includes/config.php';
    $content    = file_get_contents($configFile);

    // Update nilai-nilai di config.php
    $replacements = [
        "/define\('AUTH_USERNAME',\s*'[^']*'\)/" => "define('AUTH_USERNAME', '" . addslashes($newUsername) . "')",
        "/define\('WG_SERVER_ENDPOINT',\s*'[^']*'\)/" => "define('WG_SERVER_ENDPOINT', '" . addslashes($wgEndpoint) . "')",
        "/define\('WG_SERVER_PUBKEY',\s*'[^']*'\)/" => "define('WG_SERVER_PUBKEY', '" . addslashes($wgPubkey) . "')",
        "/define\('WG_SERVER_ADDR',\s*'[^']*'\)/" => "define('WG_SERVER_ADDR', '" . addslashes($wgAddr) . "')",
        "/define\('WG_SUBNET_PREFIX',\s*'[^']*'\)/" => "define('WG_SUBNET_PREFIX', '" . addslashes($wgPrefix) . "')",
        "/define\('WG_INTERFACE',\s*'[^']*'\)/" => "define('WG_INTERFACE', '" . addslashes($wgIface) . "')",
    ];

    if (!empty($newPassword)) {
        $replacements["/define\('AUTH_PASSWORD',\s*'[^']*'\)/"] = "define('AUTH_PASSWORD', '" . addslashes($newPassword) . "')";
    }

    foreach ($replacements as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    if (file_put_contents($configFile, $content) !== false) {
        $success = 'Pengaturan berhasil disimpan. Beberapa perubahan aktif setelah reload halaman.';
        write_log(get_db(), 'update-settings', null, null, 'Pengaturan diperbarui oleh admin');
    } else {
        $error = 'Gagal menyimpan file config. Pastikan file writable oleh web server.';
    }
}

$assetBase  = '../';
$pageTitle  = 'Pengaturan';
$activeNav  = 'settings';

include __DIR__ . '/../includes/layout_header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><span class="alert-icon">✅</span> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><span class="alert-icon">⚠️</span> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

  <!-- WireGuard Server Config -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header">
      <span class="card-title">🔗 Konfigurasi WireGuard Server</span>
    </div>
    <div class="card-body">
      <div class="info-grid" style="grid-template-columns: 1fr 1fr; margin-bottom:0;">

        <div class="form-group">
          <label class="form-label">Server Endpoint (IP:Port) <span class="required">*</span></label>
          <input type="text" name="wg_endpoint" class="form-control"
                 value="<?= htmlspecialchars(WG_SERVER_ENDPOINT) ?>"
                 placeholder="202.10.48.191:51820">
          <div class="form-hint">IP publik VPS dan port WireGuard yang didengarkan.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Server IP di Tunnel (Hub IP) <span class="required">*</span></label>
          <input type="text" name="wg_addr" class="form-control"
                 value="<?= htmlspecialchars(WG_SERVER_ADDR) ?>"
                 placeholder="10.0.0.1">
          <div class="form-hint">IP VPS di dalam jaringan tunnel WireGuard.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Subnet Prefix Tunnel <span class="required">*</span></label>
          <input type="text" name="wg_prefix" class="form-control"
                 value="<?= htmlspecialchars(WG_SUBNET_PREFIX) ?>"
                 placeholder="10.0.0.">
          <div class="form-hint">Prefix IP untuk router (diakhiri titik, misal 10.0.0.).</div>
        </div>

        <div class="form-group">
          <label class="form-label">WireGuard Interface <span class="required">*</span></label>
          <input type="text" name="wg_iface" class="form-control"
                 value="<?= htmlspecialchars(WG_INTERFACE) ?>"
                 placeholder="wg0">
          <div class="form-hint">Nama interface WireGuard di VPS (biasanya wg0).</div>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
          <label class="form-label">Server Public Key <span class="required">*</span></label>
          <input type="text" name="wg_pubkey" class="form-control"
                 value="<?= htmlspecialchars(WG_SERVER_PUBKEY) ?>"
                 placeholder="Public key dari /etc/wireguard/server_public.key"
                 style="font-family: monospace; font-size:12px;">
          <div class="form-hint">
            Didapat dari: <code>cat /etc/wireguard/server_public.key</code>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Auth Config -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <span class="card-title">🔐 Kredensial Login Dashboard</span>
    </div>
    <div class="card-body">
      <div class="info-grid" style="grid-template-columns: 1fr 1fr; margin-bottom:0;">

        <div class="form-group">
          <label class="form-label">Username <span class="required">*</span></label>
          <input type="text" name="auth_username" class="form-control"
                 value="<?= htmlspecialchars(AUTH_USERNAME) ?>"
                 autocomplete="off">
        </div>

        <div class="form-group">
          <label class="form-label">Password Baru</label>
          <input type="password" name="auth_password" class="form-control"
                 placeholder="Kosongkan jika tidak ingin ganti password"
                 autocomplete="new-password">
          <div class="form-hint">Kosongkan jika tidak ingin mengubah password.</div>
        </div>

      </div>
    </div>
  </div>

  <div style="display:flex; gap:10px;">
    <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
    <a href="../index.php" class="btn btn-secondary">Batal</a>
  </div>

</form>

<!-- Informasi Server WireGuard -->
<div class="card" style="margin-top:20px;">
  <div class="card-header">
    <span class="card-title">ℹ️ Informasi Server</span>
  </div>
  <div class="card-body">
    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">Endpoint</div>
        <div class="info-value"><code><?= htmlspecialchars(WG_SERVER_ENDPOINT) ?></code></div>
      </div>
      <div class="info-item">
        <div class="info-label">Interface</div>
        <div class="info-value"><code><?= htmlspecialchars(WG_INTERFACE) ?></code></div>
      </div>
      <div class="info-item">
        <div class="info-label">Hub IP</div>
        <div class="info-value"><code><?= htmlspecialchars(WG_SERVER_ADDR) ?></code></div>
      </div>
      <div class="info-item">
        <div class="info-label">Subnet Prefix</div>
        <div class="info-value"><code><?= htmlspecialchars(WG_SUBNET_PREFIX) ?>x/32</code></div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
