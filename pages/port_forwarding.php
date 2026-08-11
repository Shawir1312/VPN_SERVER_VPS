<?php
$assetBase = '../';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$pdo = get_db();
$routers = $pdo->query('SELECT id, name, tunnel_ip FROM routers ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$portForwards = $pdo->query('SELECT pf.*, r.name as router_name, r.tunnel_ip FROM port_forwards pf JOIN routers r ON pf.router_id = r.id ORDER BY pf.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Port Forwarding';
$activeNav = 'port_forward';

$msg = $_GET['msg'] ?? '';

include __DIR__ . '/../includes/layout_header.php';
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">📡 Daftar Port Forwarding Aktif</span>
    <form method="POST" action="../port_forwards.php" style="display:inline-block">
        <input type="hidden" name="action" value="sync">
        <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Proses ini akan menimpa ulang semua rule iptables. Lanjutkan?');">🔄 Sync iptables</button>
    </form>
  </div>

  <?php if ($msg): ?>
    <div class="alert success" style="margin: 16px;">
      <span class="alert-icon">✅</span> <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Router Target</th>
          <th>Protocol</th>
          <th>Port VPS (Publik)</th>
          <th>Port Target</th>
          <th>Dibuat Pada</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($portForwards)): ?>
        <tr class="empty-row">
          <td colspan="6">Belum ada aturan Port Forwarding.</td>
        </tr>
      <?php endif; ?>
      <?php foreach ($portForwards as $pf): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($pf['router_name']) ?></strong><br>
            <code style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($pf['tunnel_ip']) ?></code>
          </td>
          <td><span class="badge" style="text-transform:uppercase"><?= htmlspecialchars($pf['protocol']) ?></span></td>
          <td><strong><?= htmlspecialchars($pf['public_port']) ?></strong></td>
          <td><strong><?= htmlspecialchars($pf['target_port']) ?></strong></td>
          <td style="color:var(--muted)"><?= htmlspecialchars($pf['created_at']) ?></td>
          <td>
            <form method="POST" action="../port_forwards.php" style="display:inline-block;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $pf['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus port forward ini? Akses dari publik akan terputus.');">🗑 Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <div class="card-title">➕ Tambah Port Forwarding Baru</div>
  <div style="font-size:13px;color:var(--muted);margin-bottom:16px;">
    Gunakan fitur ini untuk membuka akses ke Router atau Server lokal di belakang Router.
    Misalnya: Port <strong>8291</strong> publik untuk akses Winbox, atau Port <strong>8080</strong> publik untuk akses web lokal.
  </div>

  <form method="POST" action="../port_forwards.php">
    <input type="hidden" name="action" value="add">
    
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Router Target <span class="req">*</span></label>
        <select name="router_id" class="form-control" required>
            <option value="">-- Pilih Router --</option>
            <?php foreach ($routers as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['tunnel_ip']) ?>)</option>
            <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-group">
        <label class="form-label">Protocol <span class="req">*</span></label>
        <select name="protocol" class="form-control" required>
            <option value="tcp">TCP (Default)</option>
            <option value="udp">UDP</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Port Publik VPS <span class="req">*</span></label>
        <input type="number" name="public_port" class="form-control mono" placeholder="Misal: 8080" required min="1" max="65535">
        <div class="form-hint">Port yang akan digunakan saat mengakses IP VPS.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Port Target (Lokal) <span class="req">*</span></label>
        <input type="number" name="target_port" class="form-control mono" placeholder="Misal: 80" required min="1" max="65535">
        <div class="form-hint">Port asli pada MikroTik atau perangkat di jaringan lokalnya.</div>
      </div>
    </div>

    <div style="margin-top: 16px;">
        <button type="submit" class="btn btn-primary">➕ Simpan & Terapkan</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
