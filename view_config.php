<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo   = get_db();
$id    = (int) ($_GET['id'] ?? 0);
$isNew = isset($_GET['new']);

$stmt = $pdo->prepare('SELECT * FROM routers WHERE id = ?');
$stmt->execute([$id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    http_response_code(404);
    die('Router tidak ditemukan.');
}

$endpointHost = explode(':', WG_SERVER_ENDPOINT)[0];

$routerOsConfig = "/interface wireguard\n"
    . "add name=wg-to-hub listen-port=13231 private-key=\"{$router['private_key']}\"\n\n"
    . "/ip address\n"
    . "add address={$router['tunnel_ip']}/24 interface=wg-to-hub\n\n"
    . "/interface wireguard peers\n"
    . "add interface=wg-to-hub public-key=\"" . WG_SERVER_PUBKEY . "\" endpoint-address={$endpointHost} "
    . "endpoint-port=51820 allowed-address=10.0.0.0/24 persistent-keepalive=25s";

$pageTitle = 'Config — ' . $router['name'];
$activeNav = 'dashboard';

include __DIR__ . '/includes/layout_header.php';
?>

<div style="max-width:700px;">
  <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
    <a href="index.php" class="btn btn-secondary btn-sm">← Dashboard</a>
    <h2 style="font-size:18px; font-weight:700;"><?= htmlspecialchars($router['name']) ?></h2>
  </div>

  <?php if ($isNew): ?>
  <div class="alert alert-success">
    <span class="alert-icon">✅</span>
    Router berhasil ditambahkan ke hub! Salin config di bawah ke terminal RouterOS MikroTik.
  </div>
  <?php endif; ?>

  <!-- Info -->
  <div class="info-grid" style="margin-bottom:16px;">
    <div class="info-item">
      <div class="info-label">IP Tunnel</div>
      <div class="info-value"><code><?= htmlspecialchars($router['tunnel_ip']) ?></code></div>
    </div>
    <div class="info-item">
      <div class="info-label">Lokasi</div>
      <div class="info-value"><?= htmlspecialchars($router['location'] ?: '—') ?></div>
    </div>
    <div class="info-item">
      <div class="info-label">Ditambahkan</div>
      <div class="info-value"><?= htmlspecialchars($router['created_at']) ?></div>
    </div>
  </div>

  <!-- Config -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📋 Config RouterOS (paste di terminal)</span>
      <button class="btn btn-secondary btn-sm" onclick="copyConfig()">📋 Copy Config</button>
    </div>
    <div class="card-body">
      <pre class="config-box" id="configBox"><?= htmlspecialchars($routerOsConfig) ?></pre>
      <p style="font-size:12.5px; color:var(--text-muted); margin-top:10px;">
        Paste perintah ini di <strong>New Terminal</strong> Winbox atau via SSH ke MikroTik.
        Setelah dijalankan, tunggu ~30 detik lalu cek status di
        <a href="index.php" style="color:var(--accent);">dashboard</a>.
      </p>
    </div>
  </div>

  <div style="margin-top:16px; display:flex; gap:10px;">
    <a href="pages/router_detail.php?id=<?= $router['id'] ?>" class="btn btn-secondary">
      🔍 Detail &amp; Diagnostik
    </a>
    <a href="delete_router.php?id=<?= $router['id'] ?>"
       class="btn btn-danger"
       onclick="return confirm('Hapus router ini dari tunnel?')">🗑 Hapus Router</a>
  </div>
</div>

<script>
function copyConfig() {
    const text = document.getElementById('configBox').textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target;
        btn.textContent = '✅ Tersalin!';
        setTimeout(() => btn.textContent = '📋 Copy Config', 2000);
    });
}
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
