<?php
// ============================================================
// SALIN FILE INI ke includes/config.php dan isi nilainya
// ============================================================

// Lokasi file database SQLite
define('DB_PATH', __DIR__ . '/../data/interkonek.db');

// Konfigurasi WireGuard Server
define('WG_SERVER_ADDR',     '10.0.0.1');           // IP hub di dalam tunnel
define('WG_SUBNET_PREFIX',   '10.0.0.');             // Prefix IP peer
define('WG_SUBNET_CIDR',     '/32');
define('WG_SERVER_PUBKEY',   'ISI_DENGAN_PUBLIC_KEY_SERVER');
define('WG_SERVER_ENDPOINT', 'IP_PUBLIK_VPS:51820');
define('WG_INTERFACE',       'wg0');

// Kredensial Login Dashboard
define('AUTH_USERNAME', 'admin');
define('AUTH_PASSWORD', 'GANTI_DENGAN_PASSWORD_AMAN');

// Info Aplikasi
define('APP_NAME',    'Interkonek');
define('APP_VERSION', '2.0.0');

// ... (copy sisa fungsi dari config.php asli)
