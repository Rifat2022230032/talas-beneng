<?php
// mark_notifications_read.php
// API untuk menandai semua notifikasi sebagai telah dibaca

header("Content-Type: application/json");
require_once '../config.php';

try {
    $stmt = $pdo->prepare("UPDATE notification_log SET status = 'read' WHERE status = 'unread'");
    $stmt->execute();
    
    echo json_encode([
        "status" => "success",
        "message" => "Semua notifikasi berhasil ditandai sebagai dibaca."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
