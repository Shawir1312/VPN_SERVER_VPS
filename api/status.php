<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

try {
    $pdo = get_db();
    $routers    = $pdo->query('SELECT id, public_key, tunnel_ip FROM routers')->fetchAll(PDO::FETCH_ASSOC);
    $liveStatus = get_wg_peer_status();

    $result = [];
    $onlineCount = 0;

    foreach ($routers as $r) {
        $st = $liveStatus[$r['public_key']] ?? null;
        $connected = $st && $st['connected'];
        if ($connected) $onlineCount++;

        $result[$r['id']] = [
            'connected'      => $connected,
            'endpoint'       => $st['endpoint'] ?? null,
            'last_handshake' => $st['last_handshake'] ? format_relative_time($st['last_handshake']) : 'Belum pernah',
            'rx'             => format_bytes($st['rx_bytes'] ?? 0),
            'tx'             => format_bytes($st['tx_bytes'] ?? 0),
        ];
    }

    echo json_encode([
        'ok'           => true,
        'ts'           => time(),
        'online_count' => $onlineCount,
        'total'        => count($routers),
        'routers'      => $result,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
