<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo        = get_db();
$routers    = $pdo->query('SELECT * FROM routers ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
$liveStatus = get_wg_peer_status();

$totalRouters  = count($routers);
$onlineCount   = 0;
$offlineCount  = 0;

foreach ($routers as $r) {
    $st = $liveStatus[$r['public_key']] ?? null;
    if ($st && $st['connected']) $onlineCount++;
    else $offlineCount++;
}

$pageTitle       = 'Dashboard';
$activeNav       = 'dashboard';
$topbarSubtitle  = WG_SERVER_ENDPOINT;
$topbarActions   = '<a href="add_router.php" class="btn btn-primary btn-sm">➕ Tambah Router</a>';

include __DIR__ . '/includes/layout_header.php';
?>

<!-- STATS CARDS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue">📡</div>
    <div class="stat-info">
      <div class="stat-value" id="stat-total"><?= $totalRouters ?></div>
      <div class="stat-label">Total Router</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green">✅</div>
    <div class="stat-info">
      <div class="stat-value" id="stat-online"><?= $onlineCount ?></div>
      <div class="stat-label">Online / Connected</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red">❌</div>
    <div class="stat-info">
      <div class="stat-value" id="stat-offline"><?= $offlineCount ?></div>
      <div class="stat-label">Offline / Disconnected</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">🔗</div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:16px; font-weight:700; color: var(--text-secondary)">
        <?= htmlspecialchars(WG_INTERFACE) ?>
      </div>
      <div class="stat-label">WireGuard Interface</div>
    </div>
  </div>
</div>

<!-- ROUTER TABLE -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Daftar Router</span>
    <div class="flex items-center gap-8">
      <span id="last-update" class="text-muted" style="font-size:11.5px"></span>
      <span class="badge" id="refresh-indicator">⟳ Auto-refresh: 10s</span>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data-table" id="routerTable">
      <thead>
        <tr>
          <th>Nama Router</th>
          <th>Lokasi</th>
          <th>IP Tunnel</th>
          <th>Status</th>
          <th>Handshake Terakhir</th>
          <th>Traffic ↓/↑</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="routerTableBody">
      <?php if (empty($routers)): ?>
        <tr class="empty-row">
          <td colspan="7">
            <span class="empty-icon">📡</span>
            Belum ada router terdaftar.<br>
            <a href="add_router.php" class="btn btn-primary" style="margin-top:12px; display:inline-flex;">➕ Tambah Router Pertama</a>
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($routers as $r):
        $st        = $liveStatus[$r['public_key']] ?? null;
        $connected = $st && $st['connected'];
      ?>
        <tr id="router-row-<?= $r['id'] ?>">
          <td>
            <strong><?= htmlspecialchars($r['name']) ?></strong>
          </td>
          <td><?= htmlspecialchars($r['location'] ?: '—') ?></td>
          <td><code><?= htmlspecialchars($r['tunnel_ip']) ?></code></td>
          <td id="status-<?= $r['id'] ?>">
            <span class="status-dot <?= $connected ? 'status-online' : 'status-offline' ?>">
              <?= $connected ? 'Connected' : 'Disconnected' ?>
            </span>
          </td>
          <td id="handshake-<?= $r['id'] ?>"><?= format_relative_time($st['last_handshake'] ?? null) ?></td>
          <td id="traffic-<?= $r['id'] ?>">
            ↓ <?= format_bytes($st['rx_bytes'] ?? 0) ?>
            / ↑ <?= format_bytes($st['tx_bytes'] ?? 0) ?>
          </td>
          <td>
            <a href="pages/router_detail.php?id=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">🔍 Detail</a>
            <a href="view_config.php?id=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">📄 Config</a>
            <a href="edit_router.php?id=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">✏️ Edit</a>
            <a href="delete_router.php?id=<?= $r['id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Hapus router <?= htmlspecialchars(addslashes($r['name'])) ?>?\nTunnel akan diputus dari server.')">🗑</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// ===== AJAX Live Status Refresh =====
let countdown = 10;

function updateCountdown() {
    const el = document.getElementById('refresh-indicator');
    if (el) el.textContent = `⟳ Refresh dalam ${countdown}s`;
}

function refreshStatus() {
    fetch('api/status.php')
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;

            // Update stat cards
            document.getElementById('stat-online').textContent  = data.online_count;
            document.getElementById('stat-offline').textContent = data.total - data.online_count;

            // Update each router row
            Object.entries(data.routers).forEach(([id, info]) => {
                const statusEl    = document.getElementById('status-' + id);
                const handshakeEl = document.getElementById('handshake-' + id);
                const trafficEl   = document.getElementById('traffic-' + id);

                if (statusEl) {
                    const cls = info.connected ? 'status-online' : 'status-offline';
                    const lbl = info.connected ? 'Connected' : 'Disconnected';
                    statusEl.innerHTML = `<span class="status-dot ${cls}">${lbl}</span>`;
                }
                if (handshakeEl) handshakeEl.textContent = info.last_handshake;
                if (trafficEl)   trafficEl.textContent   = `↓ ${info.rx} / ↑ ${info.tx}`;
            });

            const now = new Date();
            const ts  = now.toLocaleTimeString('id-ID');
            const el  = document.getElementById('last-update');
            if (el) el.textContent = `Diperbarui: ${ts}`;

            countdown = 10;
        })
        .catch(() => {
            // Gagal fetch — tetap countdown
        });
}

// Jalankan setiap 1 detik untuk countdown, fetch setiap 10 detik
setInterval(() => {
    countdown--;
    updateCountdown();
    if (countdown <= 0) {
        countdown = 10;
        refreshStatus();
    }
}, 1000);

updateCountdown();
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
