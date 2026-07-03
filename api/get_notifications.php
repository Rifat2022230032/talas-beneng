<?php
// get_notifications.php
// API untuk mengambil log notifikasi dengan opsi filter hari, bulan, tahun

header("Content-Type: application/json");
require_once '../config.php';

try {
    $where_clauses = [];
    $params = [];

    // Filter Hari (Tanggal spesifik)
    if (!empty($_GET['day'])) {
        $where_clauses[] = "DAY(created_at) = ?";
        $params[] = (int)$_GET['day'];
    }

    // Filter Bulan
    if (!empty($_GET['month'])) {
        $where_clauses[] = "MONTH(created_at) = ?";
        $params[] = (int)$_GET['month'];
    }

    // Filter Tahun
    if (!empty($_GET['year'])) {
        $where_clauses[] = "YEAR(created_at) = ?";
        $params[] = (int)$_GET['year'];
    }

    // Filter Status (misal: unread, read, dll)
    if (!empty($_GET['status'])) {
        $where_clauses[] = "status = ?";
        $params[] = $_GET['status'];
    }

    $where_sql = "";
    if (count($where_clauses) > 0) {
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
    }

    $query = "SELECT * FROM notification_log $where_sql ORDER BY id DESC LIMIT 100";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    echo json_encode([
        "status" => "success",
        "data" => $notifications
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
