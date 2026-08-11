<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = get_db();
$id  = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

$stmt = $pdo->prepare('SELECT * FROM routers WHERE id = ?');
$stmt->execute([$id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    die('Router tidak ditemukan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $location = trim($_POST['location'] ?? '');
    $lan_subnets = trim($_POST['lan_subnets'] ?? '');
    $notes    = trim($_POST['notes']    ?? '');

    if ($name === '') {
        $error = 'Nama router wajib diisi.';
    } else {
        try {
            // Update AllowedIPs di VPN Server (live)
            $allowedIps = $router['tunnel_ip'] . '/32';
            if ($lan_subnets !== '') {
                $cleaned_lan = implode(',', array_map('trim', explode(',', $lan_subnets)));
                $allowedIps .= ',' . $cleaned_lan;
            }
            
            // Kita panggil wg set untuk menimpa ulang allowed-ips
            $cmd = sprintf(
                'sudo wg set %s peer %s allowed-ips %s',
                escapeshellarg(WG_INTERFACE),
                escapeshellarg($router['public_key']),
                escapeshellarg($allowedIps)
            );
            cmd_exec($cmd, $out, $code);
            
            // Note: Idealnya wg0.conf juga di-update secara parsial,
            // tapi karena skrip installer tidak punya parser wg0.conf yang rumit,
            // wg set ini akan persist sampai reboot, jika saveconfig aktif maka akan tersimpan otomatis.
            // Untuk aaPanel / Debian, biasanya harus diedit manual atau pakai wg-quick save.
            cmd_exec('sudo wg-quick save ' . escapeshellarg(WG_INTERFACE), $out, $code);

            $stmt = $pdo->prepare(
                'UPDATE routers SET name = ?, location = ?, lan_subnets = ?, notes = ? WHERE id = ?'
            );
            $stmt->execute([$name, $location, $lan_subnets, $notes, $id]);
            
            write_log($pdo, 'edit-router', $id, $name, "Mengubah data router. LAN: $lan_subnets");
            $success = 'Data router berhasil diperbarui! Jika kamu mengubah IP Lokal, pastikan cek config baru di halaman Config.';
            
            // Refresh data
            $stmtSelect = $pdo->prepare('SELECT * FROM routers WHERE id = ?');
            $stmtSelect->execute([$id]);
            $router = $stmtSelect->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Router';
$activeNav = 'dashboard';
include __DIR__ . '/includes/layout_header.php';
?>

<div style="max-width:600px;">
  <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
    <a href="index.php" class="btn btn-secondary btn-sm">← Kembali</a>
    <h2 style="font-size:18px; font-weight:700;">Edit Router: <?= htmlspecialchars($router['name']) ?></h2>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error">
    <span class="alert-icon">⚠️</span> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <span class="alert-icon">✅</span> <?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label class="form-label">Nama Router <span class="required">*</span></label>
          <input type="text" name="name" class="form-control"
                 value="<?= htmlspecialchars($router['name']) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Lokasi</label>
          <input type="text" name="location" class="form-control"
                 value="<?= htmlspecialchars($router['location'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">IP Lokal / LAN Subnet</label>
          <input type="text" name="lan_subnets" class="form-control mono"
                 placeholder="Contoh: 192.168.9.0/24"
                 value="<?= htmlspecialchars($router['lan_subnets'] ?? '') ?>">
          <div class="form-hint">Kosongkan jika tidak ada. Pisahkan dengan koma jika lebih dari satu.</div>
        </div>

        <div class="form-group">
          <label class="form-label">Catatan</label>
          <textarea name="notes" class="form-control"><?= htmlspecialchars($router['notes'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
