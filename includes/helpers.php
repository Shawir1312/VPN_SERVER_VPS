<?php
require_once __DIR__ . '/config.php';

/**
 * Generate WireGuard keypair baru pakai binary `wg`.
 * Return: ['private' => ..., 'public' => ...]
 */
function generate_wg_keypair(): array {
    $private = trim(shell_exec('wg genkey'));
    if (empty($private)) {
        throw new RuntimeException('Gagal menjalankan `wg genkey`. Pastikan WireGuard tools terinstall di server.');
    }
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
    ];
    $process = proc_open('wg pubkey', $descriptors, $pipes);
    fwrite($pipes[0], $private);
    fclose($pipes[0]);
    $public = trim(stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    proc_close($process);

    return ['private' => $private, 'public' => $public];
}

/**
 * Cari IP tunnel berikutnya yang belum dipakai, mulai dari 10.0.0.2
 */
function next_available_tunnel_ip(PDO $pdo): string {
    $used = $pdo->query('SELECT tunnel_ip FROM routers')->fetchAll(PDO::FETCH_COLUMN);
    $usedLastOctets = array_map(function ($ip) {
        $parts = explode('.', $ip);
        return (int) end($parts);
    }, $used);

    for ($i = 2; $i <= 254; $i++) {
        if (!in_array($i, $usedLastOctets, true)) {
            return WG_SUBNET_PREFIX . $i;
        }
    }
    throw new RuntimeException('Subnet tunnel penuh (maksimum 253 router).');
}

/**
 * Tambahkan peer baru ke server WireGuard secara live via script privileged.
 */
function add_peer_to_server(string $publicKey, string $tunnelIp): void {
    $cmd = sprintf(
        'sudo /usr/local/bin/wg-add-peer.sh %s %s 2>&1',
        escapeshellarg($publicKey),
        escapeshellarg($tunnelIp . '/32')
    );
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Gagal menambahkan peer ke server: ' . implode("\n", $output));
    }
}

/**
 * Hapus peer dari server WireGuard.
 */
function remove_peer_from_server(string $publicKey): void {
    $cmd = sprintf('sudo /usr/local/bin/wg-remove-peer.sh %s 2>&1', escapeshellarg($publicKey));
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Gagal menghapus peer dari server: ' . implode("\n", $output));
    }
}

/**
 * Baca status live semua peer dari `wg show wg0 dump`.
 * Return array asosiatif keyed by public_key.
 */
function get_wg_peer_status(): array {
    $output = shell_exec('sudo wg show ' . WG_INTERFACE . ' dump 2>/dev/null');
    if (!$output) {
        return [];
    }

    $lines = explode("\n", trim($output));
    array_shift($lines); // baris pertama = info interface, bukan peer

    $status = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $cols = explode("\t", $line);
        if (count($cols) < 8) continue;

        [$pubKey, $psk, $endpoint, $allowedIps, $latestHandshake, $rx, $tx, $keepalive] = $cols;

        $lastHandshakeTs = (int) $latestHandshake;
        $isConnected = $lastHandshakeTs > 0 && (time() - $lastHandshakeTs) < 180;

        $status[$pubKey] = [
            'endpoint'       => $endpoint === '(none)' ? null : $endpoint,
            'connected'      => $isConnected,
            'last_handshake' => $lastHandshakeTs > 0 ? $lastHandshakeTs : null,
            'rx_bytes'       => (int) $rx,
            'tx_bytes'       => (int) $tx,
        ];
    }
    return $status;
}

/**
 * Ping IP tunnel sebuah router dari VPS. Return array hasil.
 */
function ping_router(string $tunnelIp): array {
    $ip = filter_var($tunnelIp, FILTER_VALIDATE_IP);
    if (!$ip) {
        return ['success' => false, 'output' => 'IP tidak valid.'];
    }
    $cmd = sprintf('ping -c 3 -W 2 %s 2>&1', escapeshellarg($ip));
    $output = shell_exec($cmd);
    $success = strpos($output ?? '', ' 0% packet loss') !== false;
    return ['success' => $success, 'output' => $output ?? 'Tidak ada output.'];
}

/**
 * Test apakah port API MikroTik (8728) bisa diakses lewat tunnel.
 */
function test_mikrotik_api(string $tunnelIp): array {
    $ip = filter_var($tunnelIp, FILTER_VALIDATE_IP);
    if (!$ip) {
        return ['success' => false, 'message' => 'IP tidak valid.'];
    }
    $conn = @fsockopen($ip, 8728, $errno, $errstr, 3);
    if ($conn) {
        fclose($conn);
        return ['success' => true, 'message' => 'Port 8728 (API MikroTik) terbuka ✓'];
    }
    return ['success' => false, 'message' => "Port 8728 tidak bisa diakses: $errstr ($errno)"];
}

function format_bytes(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
}

function format_relative_time(?int $timestamp): string {
    if (!$timestamp) return 'Belum pernah';
    $diff = time() - $timestamp;
    if ($diff < 60) return $diff . ' detik lalu';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    return floor($diff / 86400) . ' hari lalu';
}
