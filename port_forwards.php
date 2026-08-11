<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$pdo = get_db();

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $routerId = (int)$_POST['router_id'];
    $publicPort = (int)$_POST['public_port'];
    $targetPort = (int)$_POST['target_port'];
    $protocol = strtolower($_POST['protocol']) === 'udp' ? 'udp' : 'tcp';

    if (!$routerId || !$publicPort || !$targetPort) {
        die("Data tidak lengkap.");
    }

    try {
        $stmt = $pdo->prepare("SELECT tunnel_ip, name FROM routers WHERE id = ?");
        $stmt->execute([$routerId]);
        $router = $stmt->fetch();

        if (!$router) {
            die("Router tidak ditemukan.");
        }

        // Insert ke DB
        $stmt = $pdo->prepare("INSERT INTO port_forwards (router_id, public_port, target_port, protocol) VALUES (?, ?, ?, ?)");
        $stmt->execute([$routerId, $publicPort, $targetPort, $protocol]);
        
        // Aplikasikan rule iptables
        add_port_forward($publicPort, $router['tunnel_ip'], $targetPort, $protocol);
        
        log_event($pdo, 'PORT_FORWARD_ADD', $routerId, $router['name'], "Port $publicPort ($protocol) diarahkan ke $targetPort");
        
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
    
    header("Location: pages/port_forwarding.php?msg=Port+Forward+Berhasil+Ditambahkan");
    exit;
}

if ($action === 'delete') {
    $id = (int)$_POST['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT pf.*, r.tunnel_ip, r.name FROM port_forwards pf JOIN routers r ON pf.router_id = r.id WHERE pf.id = ?");
        $stmt->execute([$id]);
        $pf = $stmt->fetch();

        if ($pf) {
            remove_port_forward((int)$pf['public_port'], $pf['tunnel_ip'], (int)$pf['target_port'], $pf['protocol']);
            
            $stmt = $pdo->prepare("DELETE FROM port_forwards WHERE id = ?");
            $stmt->execute([$id]);
            
            log_event($pdo, 'PORT_FORWARD_DEL', $pf['router_id'], $pf['name'], "Menghapus forward port {$pf['public_port']} ({$pf['protocol']})");
        }
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
    
    header("Location: pages/port_forwarding.php?msg=Port+Forward+Dihapus");
    exit;
}

if ($action === 'sync') {
    try {
        sync_all_port_forwards($pdo);
        log_event($pdo, 'PORT_FORWARD_SYNC', null, 'System', 'Sinkronisasi ulang semua rule iptables');
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
    header("Location: pages/port_forwarding.php?msg=Sinkronisasi+Berhasil");
    exit;
}

header("Location: pages/port_forwarding.php");
exit;
