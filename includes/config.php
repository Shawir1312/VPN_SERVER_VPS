<?php
// ============================================================
// FILE INI DIGENERATE OTOMATIS OLEH install.php
// Jangan edit manual — jalankan install.php ulang jika perlu
// ============================================================

// Database MySQL
define('DB_HOST',     'localhost');
define('DB_NAME',     'vpn');
define('DB_USER',     'vpn');
define('DB_PASS',     's1312');
define('DB_CHARSET',  'utf8mb4');

// Konfigurasi WireGuard Server
define('WG_SERVER_ADDR',     '10.66.66.1');
define('WG_SUBNET_PREFIX',   '10.66.66.');
define('WG_SUBNET_CIDR',     '/32');
define('WG_SERVER_PUBKEY',   'BELUM_DISET');
define('WG_SERVER_ENDPOINT', '202.10.48.191:51820');
define('WG_INTERFACE',       'wg0');

// Kredensial Login Dashboard
define('AUTH_USERNAME', 'admin');
define('AUTH_PASSWORD', 'BELUM_DISET');

// Info Aplikasi
define('APP_NAME',    'Interkonek');
define('APP_VERSION', '2.0.0');

// ============================================================
// DATABASE — MySQL PDO
// ============================================================
function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// ============================================================
// AUTH HELPERS
// ============================================================
function require_login(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['logged_in'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /login.php?redirect=' . $redirect);
        exit;
    }
}

function is_logged_in(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['logged_in']);
}

// ============================================================
// LOG HELPER
// ============================================================
function write_log(PDO $pdo, string $event, ?int $routerId = null, ?string $routerName = null, ?string $details = null): void {
    try {
        $pdo->prepare(
            'INSERT INTO logs (event, router_id, router_name, details) VALUES (?, ?, ?, ?)'
        )->execute([$event, $routerId, $routerName, $details]);
    } catch (Exception $e) { /* jangan break aplikasi */ }
}
