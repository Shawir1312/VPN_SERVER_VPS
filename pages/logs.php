<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$pdo  = get_db();
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$total = (int) $pdo->query('SELECT COUNT(*) FROM logs')->fetchColumn();
$pages = max(1, ceil($total / $limit));

$logs = $pdo->query(
    "SELECT * FROM logs ORDER BY created_at DESC LIMIT $limit OFFSET $offset"
)->fetchAll(PDO::FETCH_ASSOC);

$assetBase    = '../';
$pageTitle    = 'Log Aktivitas';
$activeNav    = 'logs';

include __DIR__ . '/../includes/layout_header.php';

function log_event_class(string $event): string {
    if (str_contains($event, 'tambah') || str_contains($event, 'add') || str_contains($event, 'login'))
        return str_contains($event, 'login') ? 'login' : 'add';
    if (str_contains($event, 'hapus') || str_contains($event, 'delete') || str_contains($event, 'remove'))
        return 'remove';
    return 'other';
}

function log_event_icon(string $event): string {
    if (str_contains($event, 'tambah') || str_contains($event, 'add')) return '➕';
    if (str_contains($event, 'hapus') || str_contains($event, 'delete') || str_contains($event, 'remove')) return '🗑';
    if (str_contains($event, 'login')) return '🔐';
    return '📋';
}
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">📋 Log Aktivitas Sistem</span>
    <span class="badge"><?= $total ?> total entri</span>
  </div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Event</th>
          <th>Router</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr class="empty-row">
          <td colspan="4">
            <span class="empty-icon">📋</span>
            Belum ada log. Aktivitas akan tercatat di sini.
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td style="white-space:nowrap; color:var(--text-muted); font-size:12.5px;">
            <?= htmlspecialchars($log['created_at']) ?>
          </td>
          <td>
            <span class="log-event <?= log_event_class($log['event']) ?>">
              <?= log_event_icon($log['event']) ?> <?= htmlspecialchars($log['event']) ?>
            </span>
          </td>
          <td>
            <?php if ($log['router_name']): ?>
              <span style="font-weight:600;"><?= htmlspecialchars($log['router_name']) ?></span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12.5px; color:var(--text-secondary);">
            <?= htmlspecialchars($log['details'] ?: '—') ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div style="padding:16px; display:flex; gap:8px; justify-content:center; border-top:1px solid var(--border);">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary btn-sm">← Sebelumnya</a>
    <?php endif; ?>
    <span class="badge" style="line-height:32px;">Halaman <?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?>
      <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary btn-sm">Berikutnya →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/layout_footer.php'; ?>
