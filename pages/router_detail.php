<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$pdo = get_db();
$id  = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM routers WHERE id = ?');
$stmt->execute([$id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    http_response_code(404);
    include __DIR__ . '/../includes/layout_header.php';
    echo '<div class="alert alert-error"><span class="alert-icon">⚠️</span>Router tidak ditemukan.</div>';
    include __DIR__ . '/../includes/layout_footer.php';
    exit;
}

$liveStatus = get_wg_peer_status();
$st         = $liveStatus[$router['public_key']] ?? null;
$connected  = $st && $st['connected'];

$endpointHost  = explode(':', WG_SERVER_ENDPOINT)[0];
$routerOsConfig = "/interface wireguard\n"
    . "add name=wg-to-hub listen-port=13231 private-key=\"{$router['private_key']}\"\n\n"
    . "/ip address\n"
    . "add address={$router['tunnel_ip']}/24 interface=wg-to-hub\n\n"
    . "/interface wireguard peers\n"
    . "add interface=wg-to-hub public-key=\"" . WG_SERVER_PUBKEY . "\" endpoint-address={$endpointHost} "
    . "endpoint-port=51820 allowed-address=10.0.0.0/24 persistent-keepalive=25s";

$assetBase  = '../';
$pageTitle  = htmlspecialchars($router['name']);
$activeNav  = 'dashboard';
$topbarSubtitle = 'Detail Router';

include __DIR__ . '/../includes/layout_header.php';
?>

<div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
  <a href="../index.php" class="btn btn-secondary btn-sm">← Kembali</a>
  <h2 style="font-size:18px; font-weight:700;"><?= htmlspecialchars($router['name']) ?></h2>
  <span class="status-dot <?= $connected ? 'status-online' : 'status-offline' ?>" id="detail-status">
    <?= $connected ? 'Connected' : 'Disconnected' ?>
  </span>
</div>

<!-- INFO GRID -->
<div class="info-grid" style="margin-bottom:20px;">
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
  <div class="info-item">
    <div class="info-label">Handshake Terakhir</div>
    <div class="info-value" id="detail-handshake"><?= format_relative_time($st['last_handshake'] ?? null) ?></div>
  </div>
  <div class="info-item">
    <div class="info-label">Endpoint MikroTik</div>
    <div class="info-value" id="detail-endpoint"><?= htmlspecialchars($st['endpoint'] ?? '—') ?></div>
  </div>
  <div class="info-item">
    <div class="info-label">Traffic ↓ / ↑</div>
    <div class="info-value" id="detail-traffic">
      <?= format_bytes($st['rx_bytes'] ?? 0) ?> / <?= format_bytes($st['tx_bytes'] ?? 0) ?>
    </div>
  </div>
</div>

<!-- PUBLIC KEY -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <span class="card-title">🔑 Public Key Router</span>
  </div>
  <div class="card-body">
    <code style="word-break:break-all; font-size:13px;"><?= htmlspecialchars($router['public_key']) ?></code>
  </div>
</div>

<!-- TOOLS: PING & API TEST -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <span class="card-title">🛠 Tools Diagnostik</span>
  </div>
  <div class="card-body">
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <button class="btn btn-secondary" id="btnPing" onclick="runAction('ping')">
        🏓 Ping Router
      </button>
      <button class="btn btn-secondary" id="btnApi" onclick="runAction('test_api')">
        🔌 Test Port API (8728)
      </button>
    </div>
    <div id="actionResult" style="margin-top:12px; display:none;"></div>
  </div>
</div>

<!-- CONFIG ROUTEROS -->
<div class="card">
  <div class="card-header">
    <span class="card-title">📋 Config RouterOS</span>
    <button class="btn btn-secondary btn-sm" onclick="copyConfig()">📋 Copy</button>
  </div>
  <div class="card-body">
    <pre class="config-box" id="configBox"><?= htmlspecialchars($routerOsConfig) ?></pre>
    <p style="font-size:12.5px; color:var(--text-muted); margin-top:10px;">
      Paste perintah ini di terminal RouterOS (Winbox → New Terminal atau SSH ke MikroTik).
      Setelah itu tunggu ±30 detik — status akan berubah <strong style="color:var(--green)">Connected</strong>.
    </p>
  </div>
</div>

<script>
const routerId = <?= $router['id'] ?>;

function runAction(action) {
    const resultEl = document.getElementById('actionResult');
    const btnEl    = document.getElementById(action === 'ping' ? 'btnPing' : 'btnApi');
    const origText = btnEl.innerHTML;

    btnEl.disabled  = true;
    btnEl.innerHTML = '<span class="spinner"></span> Memproses...';
    resultEl.style.display = 'none';

    fetch('../api/router_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}&router_id=${routerId}`
    })
    .then(r => r.json())
    .then(data => {
        btnEl.disabled  = false;
        btnEl.innerHTML = origText;
        resultEl.style.display = 'block';

        if (action === 'ping') {
            const cls = data.success ? 'alert-success' : 'alert-error';
            const icon = data.success ? '✅' : '❌';
            resultEl.innerHTML = `
                <div class="alert ${cls}">
                    <span class="alert-icon">${icon}</span>
                    ${data.success ? 'Ping berhasil!' : 'Ping gagal — router tidak merespons.'}
                </div>
                <div class="ping-output">${(data.output || '').replace(/</g,'&lt;')}</div>
            `;
        } else {
            const cls  = data.success ? 'alert-success' : 'alert-error';
            const icon = data.success ? '✅' : '❌';
            resultEl.innerHTML = `
                <div class="alert ${cls}">
                    <span class="alert-icon">${icon}</span>
                    ${data.message || data.error || 'Tidak ada respons.'}
                </div>
            `;
        }
    })
    .catch(err => {
        btnEl.disabled  = false;
        btnEl.innerHTML = origText;
        resultEl.style.display = 'block';
        resultEl.innerHTML = `<div class="alert alert-error"><span class="alert-icon">⚠️</span>Error: ${err}</div>`;
    });
}

function copyConfig() {
    const text = document.getElementById('configBox').textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target;
        btn.textContent = '✅ Tersalin!';
        setTimeout(() => btn.textContent = '📋 Copy', 2000);
    });
}
</script>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
