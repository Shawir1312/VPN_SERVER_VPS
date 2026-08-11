<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

header('Content-Type: application/json');

$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$routerId = (int) ($_POST['router_id'] ?? $_GET['router_id'] ?? 0);

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM routers WHERE id = ?');
    $stmt->execute([$routerId]);
    $router = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$router) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Router tidak ditemukan.']);
        exit;
    }

    if ($action === 'ping') {
        $result = ping_router($router['tunnel_ip']);
        echo json_encode([
            'ok'      => true,
            'success' => $result['success'],
            'output'  => $result['output'],
        ]);

    } elseif ($action === 'test_api') {
        $result = test_mikrotik_api($router['tunnel_ip']);
        echo json_encode([
            'ok'      => true,
            'success' => $result['success'],
            'message' => $result['message'],
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Action tidak dikenal.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
