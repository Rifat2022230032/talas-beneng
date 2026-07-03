<?php
// mark_single_read.php
// API untuk menandai satu notifikasi sebagai telah dibaca

header("Content-Type: application/json");
require_once '../config.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);

if ($id === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "ID notifikasi diperlukan."]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE notification_log SET status = 'read' WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        "status" => "success",
        "message" => "Notifikasi berhasil ditandai sebagai dibaca."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
