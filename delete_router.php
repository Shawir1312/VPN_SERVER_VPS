<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = get_db();
$id  = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM routers WHERE id = ?');
$stmt->execute([$id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    header('Location: index.php?error=notfound');
    exit;
}

try {
    remove_peer_from_server($router['public_key']);
} catch (Exception $e) {
    // Lanjutkan hapus dari DB meski script gagal
    // (misal peer sudah tidak ada di server)
}

$pdo->prepare('DELETE FROM routers WHERE id = ?')->execute([$id]);

write_log($pdo, 'hapus-router', $id, $router['name'],
    "IP Tunnel: {$router['tunnel_ip']} | Lokasi: {$router['location']}");

header('Location: index.php?deleted=1');
exit;
