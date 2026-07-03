<?php
// save_wifi.php
// API untuk memperbarui pengaturan WiFi (SSID dan Password) di database setelah melakukan validasi status perangkat

header("Content-Type: application/json");
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Gunakan POST."]);
    exit;
}

$wifi_ssid = isset($_POST['wifi_ssid']) ? trim($_POST['wifi_ssid']) : '';
$wifi_password = isset($_POST['wifi_password']) ? trim($_POST['wifi_password']) : '';

if (empty($wifi_ssid)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "SSID WiFi tidak boleh kosong."]);
    exit;
}

try {
    // 1. Periksa status online perangkat IoT Utama (batas 30 detik)
    $stmt_sensor = $pdo->query("SELECT created_at FROM sensor_log ORDER BY id DESC LIMIT 1");
    $latest_sensor = $stmt_sensor->fetch();
    
    date_default_timezone_set('Asia/Jakarta');
    $now = time();
    $esp_online = false;
    
    if ($latest_sensor) {
        $diff = $now - strtotime($latest_sensor['created_at']);
        if ($diff <= 30) {
            $esp_online = true;
        }
    }
    
    // 2. Periksa status online perangkat kamera ESP32-CAM (batas 30 detik)
    $stmt_cam = $pdo->query("SELECT created_at FROM camera_log ORDER BY id DESC LIMIT 1");
    $latest_cam = $stmt_cam->fetch();
    $cam_online = false;
    
    if ($latest_cam) {
        $diff_cam = $now - strtotime($latest_cam['created_at']);
        if ($diff_cam <= 30) {
            $cam_online = true;
        }
    }
    
    // 3. Validasi keharusan kedua perangkat online
    if (!$esp_online || !$cam_online) {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "SSID dan Password tidak dapat diubah karena perangkat IoT atau kamera sedang offline."
        ]);
        exit;
    }
    
    // 4. Simpan konfigurasi WiFi baru ke database
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO settings (key_name, value_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_val = ?");
    $stmt->execute(['wifi_ssid', $wifi_ssid, $wifi_ssid]);
    $stmt->execute(['wifi_password', $wifi_password, $wifi_password]);
    $pdo->commit();
    
    // Log notifikasi perubahan WiFi
    addNotification('WARNING', "Konfigurasi WiFi diperbarui. Perangkat akan beralih ke SSID baru: '{$wifi_ssid}'.");
    
    echo json_encode([
        "status" => "success",
        "message" => "Konfigurasi WiFi berhasil disimpan. Perangkat IoT akan mengambil konfigurasi baru secara sinkron."
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
