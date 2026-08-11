<?php
/**
 * Shared layout header — include di atas setiap halaman setelah require_login().
 * Param: $pageTitle (string) — judul halaman untuk topbar
 *        $activeNav  (string) — 'dashboard'|'logs'|'settings'
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav  = $activeNav  ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= $assetBase ?? '' ?>assets/style.css">
</head>
<body>
<div class="app-shell">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🔗</div>
      <div>
        <div class="logo-text"><?= APP_NAME ?></div>
        <div class="logo-version">v<?= APP_VERSION ?></div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Menu</div>
      <a href="<?= $assetBase ?? '' ?>index.php"
         class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
        <span class="nav-icon">📡</span> Dashboard
      </a>
      <a href="<?= $assetBase ?? '' ?>add_router.php"
         class="nav-item <?= $activeNav === 'add_router' ? 'active' : '' ?>">
        <span class="nav-icon">➕</span> Tambah Router
      </a>

      <div class="nav-section-title">Sistem</div>
      <a href="<?= $assetBase ?? '' ?>pages/port_forwarding.php"
         class="nav-item <?= $activeNav === 'port_forward' ? 'active' : '' ?>">
        <span class="nav-icon">📡</span> Port Forwarding
      </a>
      <a href="<?= $assetBase ?? '' ?>pages/logs.php"
         class="nav-item <?= $activeNav === 'logs' ? 'active' : '' ?>">
        <span class="nav-icon">📋</span> Log Aktivitas
      </a>
      <a href="<?= $assetBase ?? '' ?>pages/settings.php"
         class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>">
        <span class="nav-icon">⚙️</span> Pengaturan
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="avatar">A</div>
        <div>
          <div class="user-name"><?= htmlspecialchars(AUTH_USERNAME) ?></div>
        </div>
        <a href="<?= $assetBase ?? '' ?>logout.php" class="logout-link" title="Logout">⏏</a>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="main">
    <header class="topbar">
      <button class="hamburger" id="hamburger" aria-label="Toggle sidebar">☰</button>
      <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
      <?php if (!empty($topbarSubtitle)): ?>
        <span class="topbar-subtitle">· <?= htmlspecialchars($topbarSubtitle) ?></span>
      <?php endif; ?>
      <div class="topbar-actions">
        <?php if (!empty($topbarActions)) echo $topbarActions; ?>
        <span class="badge" id="wg-status-badge" title="Status WireGuard interface">wg0</span>
      </div>
    </header>

    <div class="content">
