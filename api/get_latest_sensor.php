<?php
// get_latest_sensor.php
// API untuk mengambil data sensor terbaru dalam format JSON

header("Content-Type: application/json");
require_once '../config.php';

try {
    // Ambil data sensor terbaru
    $stmt = $pdo->query("SELECT * FROM sensor_log ORDER BY id DESC LIMIT 1");
    $latest = $stmt->fetch();

    if ($latest) {
        // Hitung status online/offline ESP32 (batas 30 detik)
        date_default_timezone_set('Asia/Jakarta');
        $last_update = strtotime($latest['created_at']);
        $now = time();
        $diff = $now - $last_update;

        $esp32_status = ($diff <= 30) ? 'Online' : 'Offline';

        // Ambil status kamera terbaru
        $stmt_cam = $pdo->query("SELECT created_at FROM camera_log ORDER BY id DESC LIMIT 1");
        $latest_cam = $stmt_cam->fetch();
        
        $cam_status = 'Offline';
        if ($latest_cam) {
            $last_cam_update = strtotime($latest_cam['created_at']);
            $diff_cam = $now - $last_cam_update;
            // Jika ada upload foto dalam 30 detik terakhir, status kamera online
            $cam_status = ($diff_cam <= 30) ? 'Online' : 'Offline';
        }

        // Ambil pengaturan suhu/kelembapan ter-update
        $settings = getSystemSettings();

        echo json_encode([
            "status" => "success",
            "data" => [
                "id" => $latest['id'],
                "temperature" => (float)$latest['temperature'],
                "humidity" => (float)$latest['humidity'],
                "sht_temperature" => (float)$latest['sht_temperature'],
                "exhaust" => (int)$latest['exhaust'],
                "exhaust_1" => (int)($latest['exhaust_1'] ?? 0),
                "exhaust_2" => (int)($latest['exhaust_2'] ?? 0),
                "exhaust_3" => (int)($latest['exhaust_3'] ?? 0),
                "exhaust_4" => (int)($latest['exhaust_4'] ?? 0),
                "mode" => ($settings['control_mode'] === 'MANUAL' ? 1 : 0),
                "exhaust_1_control" => ($settings['exhaust_1_control'] === 'ON' ? 1 : 0),
                "exhaust_2_control" => ($settings['exhaust_2_control'] === 'ON' ? 1 : 0),
                "exhaust_3_control" => ($settings['exhaust_3_control'] === 'ON' ? 1 : 0),
                "exhaust_4_control" => ($settings['exhaust_4_control'] === 'ON' ? 1 : 0),
                "wifi" => (int)$latest['wifi'],
                "created_at" => $latest['created_at'],
                "time_ago" => $diff . " detik lalu",
                "esp32_status" => $esp32_status,
                "cam_status" => $cam_status,
                "thresholds" => [
                    "suhu_maks" => (float)$settings['suhu_maks'],
                    "hum_maks" => (float)$settings['hum_maks'],
                    "hum_min" => (float)$settings['hum_min']
                ]
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Belum ada data sensor masuk.",
            "data" => [
                "esp32_status" => "Offline",
                "cam_status" => "Offline"
            ]
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
