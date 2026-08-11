<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo          = get_db();
$error        = '';
$newRouterId  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $location    = trim($_POST['location'] ?? '');
    $lan_subnets = trim($_POST['lan_subnets'] ?? '');
    $notes       = trim($_POST['notes']    ?? '');

    if ($name === '') {
        $error = 'Nama router wajib diisi.';
    } else {
        try {
            $keypair   = generate_wg_keypair();
            $tunnelIp  = next_available_tunnel_ip($pdo);

            $allowedIps = $tunnelIp . '/32';
            if ($lan_subnets !== '') {
                // Bersihkan spasi berlebih
                $cleaned_lan = implode(',', array_map('trim', explode(',', $lan_subnets)));
                $allowedIps .= ',' . $cleaned_lan;
            }

            add_peer_to_server($keypair['public'], $allowedIps);

            // Tambahkan rute ke kernel VPS agar paket diteruskan ke wg0
            if ($lan_subnets !== '') {
                $lans = array_filter(array_map('trim', explode(',', $lan_subnets)));
                foreach ($lans as $lan) {
                    cmd_exec('sudo ip route replace ' . escapeshellarg($lan) . ' dev ' . escapeshellarg(WG_INTERFACE), $out, $code);
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO routers (name, location, public_key, private_key, tunnel_ip, lan_subnets, notes) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $location, $keypair['public'], $keypair['private'], $tunnelIp, $lan_subnets, $notes]);
            $newRouterId = $pdo->lastInsertId();

            write_log($pdo, 'tambah-router', (int) $newRouterId, $name,
                "IP Tunnel: $tunnelIp | Lokasi: $location");

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if ($newRouterId) {
    header('Location: view_config.php?id=' . $newRouterId . '&new=1');
    exit;
}

$pageTitle  = 'Tambah Router';
$activeNav  = 'add_router';

include __DIR__ . '/includes/layout_header.php';
?>

<div style="max-width:600px;">
  <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
    <a href="index.php" class="btn btn-secondary btn-sm">← Kembali</a>
    <h2 style="font-size:18px; font-weight:700;">Tambah Router Baru</h2>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error">
    <span class="alert-icon">⚠️</span> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <span class="card-title">📡 Informasi Router</span>
    </div>
    <div class="card-body">
      <form method="POST" id="addForm">

        <div class="form-group">
          <label class="form-label">Nama Router <span class="required">*</span></label>
          <input type="text" name="name" class="form-control"
                 placeholder="Contoh: MikroTik Cabang Jailolo"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                 required autofocus>
        </div>

        <div class="form-group">
          <label class="form-label">Lokasi (opsional)</label>
          <input type="text" name="location" class="form-control"
                 placeholder="Contoh: Jailolo, Halmahera Barat"
                 value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">IP Lokal / LAN Subnet (opsional)</label>
          <input type="text" name="lan_subnets" class="form-control mono"
                 placeholder="Contoh: 192.168.9.0/24, 192.168.10.0/24"
                 value="<?= htmlspecialchars($_POST['lan_subnets'] ?? '') ?>">
          <div class="form-hint">Pisahkan dengan koma jika lebih dari satu. IP ini akan dirouting agar bisa diakses dari cabang lain.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Catatan (opsional)</label>
          <textarea name="notes" class="form-control"
                    placeholder="Info tambahan tentang router ini..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>

        <div class="alert alert-info" style="margin-bottom:16px;">
          <span class="alert-icon">ℹ️</span>
          Keypair WireGuard akan digenerate otomatis. IP tunnel berikutnya yang tersedia:
          <strong><code><?= next_available_tunnel_ip($pdo) ?></code></strong>
        </div>

        <button type="submit" class="btn btn-primary" id="submitBtn">
          🔑 Generate Keypair &amp; Tambahkan
        </button>

      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('addForm').addEventListener('submit', () => {
    const btn = document.getElementById('submitBtn');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> Memproses...';
});
</script>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
