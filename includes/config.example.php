<?php
// ============================================================
// SALIN FILE INI ke includes/config.php dan isi nilainya
// Ganti semua nilai yang bertuliskan ISI_DENGAN_...
// ============================================================

// Lokasi file database SQLite
define('DB_PATH', __DIR__ . '/../data/interkonek.db');

// Konfigurasi WireGuard Server
define('WG_SERVER_ADDR',     '10.66.66.1');                    // IP VPS di dalam tunnel
define('WG_SUBNET_PREFIX',   '10.66.66.');                     // Peer dapat 10.66.66.2, .3, dst
define('WG_SUBNET_CIDR',     '/32');
define('WG_SERVER_PUBKEY',   'ISI_DENGAN_PUBLIC_KEY_SERVER');  // cat /etc/wireguard/server_public.key
define('WG_SERVER_ENDPOINT', 'ISI_IP_PUBLIK_VPS:51820');       // contoh: 202.10.48.191:51820
define('WG_INTERFACE',       'wg0');

// Kredensial Login Dashboard
define('AUTH_USERNAME', 'admin');
define('AUTH_PASSWORD', 'ISI_DENGAN_PASSWORD_AMAN');

// Info Aplikasi
define('APP_NAME',    'Interkonek');
define('APP_VERSION', '2.0.0');

// ============================================================
// DATABASE
// ============================================================
function get_db(): PDO {
    $dataDir = dirname(DB_PATH);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0770, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(file_get_contents(__DIR__ . '/../schema.sql'));
    return $pdo;
}

// ============================================================
// AUTH HELPERS
// ============================================================
function require_login(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['logged_in'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?redirect=' . $redirect);
        exit;
    }
}

function is_logged_in(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['logged_in']);
}

// ============================================================
// LOG HELPER
// ============================================================
function write_log(PDO $pdo, string $event, ?int $routerId = null, ?string $routerName = null, ?string $details = null): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO logs (event, router_id, router_name, details) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$event, $routerId, $routerName, $details]);
    } catch (Exception $e) {
        // Logging gagal tidak boleh break aplikasi utama
    }
}
