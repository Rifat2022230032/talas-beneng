<?php
// delete_sensors.php
// API untuk menghapus data riwayat sensor (semua atau beberapa terpilih) dari database

header("Content-Type: application/json");
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$ids = isset($_POST['ids']) ? $_POST['ids'] : [];

try {
    if ($action === 'all') {
        // Hapus semua log sensor di database
        $pdo->exec("DELETE FROM sensor_log");
        
        // Tambah notifikasi sistem
        addNotification('INFO', 'Semua riwayat data sensor berhasil dibersihkan.');
        
        echo json_encode([
            "status" => "success",
            "message" => "Semua riwayat data sensor berhasil dikosongkan."
        ]);
        exit;
    } 
    
    // Hapus data sensor yang terpilih saja
    if (!empty($ids) && is_array($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $stmt_delete = $pdo->prepare("DELETE FROM sensor_log WHERE id IN ($placeholders)");
        $stmt_delete->execute($ids);
        
        addNotification('INFO', count($ids) . ' baris riwayat data sensor berhasil dihapus.');
        
        echo json_encode([
            "status" => "success",
            "message" => count($ids) . " data sensor terpilih berhasil dihapus."
        ]);
        exit;
    }
    
    echo json_encode(["status" => "error", "message" => "Aksi tidak dikenali atau tidak ada data terpilih."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
