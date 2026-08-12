<?php
require_once __DIR__ . '/config.php';

// ============================================================
// FUNGSI BANTUAN
// ============================================================

function cmd_shell_exec(string $cmd): string {
    if (function_exists('shell_exec')) {
        return (string) shell_exec($cmd);
    } elseif (function_exists('exec')) {
        exec($cmd, $out);
        return implode("\n", $out);
    }
    throw new RuntimeException("⚠️ ERROR AAPANEL: Fungsi shell_exec dinonaktifkan.\nCara fix: Buka aaPanel -> App Store -> PHP -> Setting -> Disabled functions -> hapus 'shell_exec' dan 'exec' dari daftar -> Save -> Restart PHP.");
}

function cmd_exec(string $cmd, &$output, &$exitCode): void {
    if (function_exists('exec')) {
        exec($cmd, $output, $exitCode);
        return;
    } elseif (function_exists('shell_exec')) {
        $res = shell_exec($cmd);
        $output = explode("\n", trim($res ?? ''));
        $exitCode = 0;
        return;
    }
    throw new RuntimeException("⚠️ ERROR AAPANEL: Fungsi exec dinonaktifkan.\nCara fix: Buka aaPanel -> App Store -> PHP -> Setting -> Disabled functions -> hapus 'shell_exec' dan 'exec' dari daftar -> Save -> Restart PHP.");
}

/**
 * Generate WireGuard keypair baru pakai binary `wg`.
 * Return: ['private' => ..., 'public' => ...]
 */
function generate_wg_keypair(): array {
    $private = trim(cmd_shell_exec('wg genkey'));
    if (empty($private)) {
        throw new RuntimeException('Gagal menjalankan `wg genkey`. Pastikan WireGuard tools terinstall di server.');
    }
    $public = trim(cmd_shell_exec('echo ' . escapeshellarg($private) . ' | wg pubkey'));
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
        escapeshellarg($tunnelIp)
    );
    cmd_exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Gagal menambahkan peer ke server: ' . implode("\n", $output));
    }
}

/**
 * Hapus peer dari server WireGuard.
 */
function remove_peer_from_server(string $publicKey): void {
    $cmd = sprintf('sudo /usr/local/bin/wg-remove-peer.sh %s 2>&1', escapeshellarg($publicKey));
    cmd_exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Gagal menghapus peer dari server: ' . implode("\n", $output));
    }
}

/**
 * Baca status live semua peer dari `wg show wg0 dump`.
 * Return array asosiatif keyed by public_key.
 */
function get_wg_peer_status(): array {
    try {
        $output = cmd_shell_exec('sudo wg show ' . WG_INTERFACE . ' dump 2>/dev/null');
    } catch (Exception $e) {
        return []; // Jangan crash halaman utama, biarkan kosong
    }
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
    try {
        $output = cmd_shell_exec($cmd);
    } catch (Exception $e) {
        return ['success' => false, 'output' => $e->getMessage()];
    }
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

/**
 * Tambah iptables port forwarding dari public VPS ke tunnel IP
 */
function add_port_forward(int $publicPort, string $tunnelIp, int $targetPort, string $protocol = 'tcp'): void {
    $protocol = strtolower($protocol) === 'udp' ? 'udp' : 'tcp';
    
    // Buka port di firewall (INPUT)
    $cmdInput = sprintf(
        'sudo iptables -I INPUT -p %s --dport %d -j ACCEPT',
        $protocol, $publicPort
    );
    cmd_exec($cmdInput, $out, $code);

    // PREROUTING: arahkan port publik ke IP tunnel
    $cmdPre = sprintf(
        'sudo iptables -t nat -A PREROUTING -p %s --dport %d -j DNAT --to-destination %s:%d',
        $protocol, $publicPort, escapeshellarg($tunnelIp), $targetPort
    );
    cmd_exec($cmdPre, $out, $code);
    
    // POSTROUTING: masquerade
    $cmdPost = sprintf(
        'sudo iptables -t nat -A POSTROUTING -p %s -d %s --dport %d -j MASQUERADE',
        $protocol, escapeshellarg($tunnelIp), $targetPort
    );
    cmd_exec($cmdPost, $out, $code);
}

/**
 * Hapus iptables port forwarding
 */
function remove_port_forward(int $publicPort, string $tunnelIp, int $targetPort, string $protocol = 'tcp'): void {
    $protocol = strtolower($protocol) === 'udp' ? 'udp' : 'tcp';
    
    $cmdInput = sprintf(
        'sudo iptables -D INPUT -p %s --dport %d -j ACCEPT',
        $protocol, $publicPort
    );
    cmd_exec($cmdInput, $out, $code);

    $cmdPre = sprintf(
        'sudo iptables -t nat -D PREROUTING -p %s --dport %d -j DNAT --to-destination %s:%d',
        $protocol, $publicPort, escapeshellarg($tunnelIp), $targetPort
    );
    cmd_exec($cmdPre, $out, $code);
    
    $cmdPost = sprintf(
        'sudo iptables -t nat -D POSTROUTING -p %s -d %s --dport %d -j MASQUERADE',
        $protocol, escapeshellarg($tunnelIp), $targetPort
    );
    cmd_exec($cmdPost, $out, $code);
}

/**
 * Sinkronisasi semua aturan port forwarding dari DB (panggil saat startup atau sync manual)
 */
function sync_all_port_forwards(PDO $pdo): void {
    $stmt = $pdo->query("SELECT pf.*, r.tunnel_ip FROM port_forwards pf JOIN routers r ON pf.router_id = r.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $finalTargetIp = !empty($row['target_ip']) ? $row['target_ip'] : $row['tunnel_ip'];
        // Hapus (ignore error jika tidak ada) lalu pasang lagi untuk menghindari duplikasi
        remove_port_forward((int)$row['public_port'], $finalTargetIp, (int)$row['target_port'], $row['protocol']);
        add_port_forward((int)$row['public_port'], $finalTargetIp, (int)$row['target_port'], $row['protocol']);
    }
}
