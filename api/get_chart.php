<?php
// get_chart.php
// API untuk mengambil data sensor historis terakhir untuk kebutuhan grafik Chart.js

header("Content-Type: application/json");
require_once '../config.php';

try {
    // Ambil 15 data sensor terbaru, urutkan dari yang terlama ke terbaru untuk grafik linear
    $stmt = $pdo->query("SELECT id, temperature, humidity, sht_temperature, created_at FROM sensor_log ORDER BY id DESC LIMIT 15");
    $rows = $stmt->fetchAll();
    
    // Balik urutan agar berurutan secara kronologis (dari kiri ke kanan pada grafik)
    $rows = array_reverse($rows);

    $labels = [];
    $temp_ds = [];
    $temp_sht = [];
    $humidity = [];

    foreach ($rows as $row) {
        // Ambil format jam menit detik untuk label X-axis grafik
        $labels[] = date('H:i:s', strtotime($row['created_at']));
        $temp_ds[] = (float)$row['temperature'];
        $temp_sht[] = (float)$row['sht_temperature'];
        $humidity[] = (float)$row['humidity'];
    }

    echo json_encode([
        "status" => "success",
        "labels" => $labels,
        "datasets" => [
            "temperature_ds" => $temp_ds,
            "temperature_sht" => $temp_sht,
            "humidity" => $humidity
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
