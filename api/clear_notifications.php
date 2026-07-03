<?php
// clear_notifications.php
// API untuk menghapus semua log notifikasi dari database

header("Content-Type: application/json");
require_once '../config.php';

try {
    $stmt = $pdo->prepare("DELETE FROM notification_log");
    $stmt->execute();
    
    echo json_encode([
        "status" => "success",
        "message" => "Semua log notifikasi berhasil dihapus."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
