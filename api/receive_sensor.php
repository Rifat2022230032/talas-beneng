<?php
// receive_sensor.php
// API untuk menerima data sensor dari ESP32 utama

header("Content-Type: application/json");
require_once '../config.php';

// Memastikan request menggunakan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Gunakan POST."]);
    exit;
}

// Mengambil data dari parameter POST
$temperature = isset($_POST['temperature']) ? (float)$_POST['temperature'] : null;
$humidity = isset($_POST['humidity']) ? (float)$_POST['humidity'] : null;
$sht_temperature = isset($_POST['sht_temperature']) ? (float)$_POST['sht_temperature'] : null;
$exhaust = isset($_POST['exhaust']) ? (int)$_POST['exhaust'] : null;
$wifi = isset($_POST['wifi']) ? (int)$_POST['wifi'] : null;

// Validasi data input
if ($temperature === null || $humidity === null || $sht_temperature === null || $exhaust === null || $wifi === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data input tidak lengkap."]);
    exit;
}

try {
    // Ambil setting threshold ter-update dari database
    $settings = getSystemSettings();
    $suhu_maks = (float)$settings['suhu_maks'];
    $hum_maks = (float)$settings['hum_maks'];
    $hum_min = (float)$settings['hum_min'];

    // 1. Validasi nilai error sensor (misal DS18B20 mengembalikan -127.0 atau SHT31 error)
    if ($temperature == -127.0 || $temperature == -999 || $humidity < 0 || $humidity > 100 || $sht_temperature == -999) {
        addNotification('ERROR', 'Sensor gagal dibaca! Periksa koneksi pin sensor pada perangkat.');
    }

    // 2. Analisis batas threshold suhu dan kelembapan
    $suhu_tertinggi = max($temperature, $sht_temperature);
    if ($suhu_tertinggi > $suhu_maks) {
        addNotification('WARNING', 'Suhu ruang pengering terlalu tinggi: ' . number_format($suhu_tertinggi, 1) . '°C (Batas: ' . $suhu_maks . '°C).');
    }
    
    if ($humidity > $hum_maks) {
        addNotification('WARNING', 'Kelembapan terlalu tinggi: ' . number_format($humidity, 1) . '%RH (Batas atas: ' . $hum_maks . '%RH).');
    } elseif ($humidity < $hum_min) {
        addNotification('INFO', 'Kelembapan target tercapai: ' . number_format($humidity, 1) . '%RH (Batas bawah: ' . $hum_min . '%RH).');
    }

    // 3. Analisis kekuatan WiFi
    if ($wifi < -85) {
        addNotification('WARNING', 'Sinyal WiFi lemah pada ESP32: ' . $wifi . ' dBm.');
    }

    // 4. Deteksi perubahan status exhaust
    // Ambil status exhaust terakhir dari database
    $stmt_last = $pdo->query("SELECT exhaust FROM sensor_log ORDER BY id DESC LIMIT 1");
    $last_log = $stmt_last->fetch();
    if ($last_log) {
        if ($last_log['exhaust'] == 0 && $exhaust == 1) {
            addNotification('SUCCESS', 'Kipas Exhaust dinyalakan (ON).');
        } elseif ($last_log['exhaust'] == 1 && $exhaust == 0) {
            addNotification('INFO', 'Kipas Exhaust dimatikan (OFF).');
        }
    }

    // 5. Simpan data sensor ke tabel sensor_log
    $stmt = $pdo->prepare("INSERT INTO sensor_log (temperature, humidity, sht_temperature, exhaust, wifi) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$temperature, $humidity, $sht_temperature, $exhaust, $wifi]);

    // Respon sukses dengan mengirimkan konfigurasi terbaru agar dibaca oleh ESP32
    echo json_encode([
        "status" => "success",
        "suhu_maks" => $suhu_maks,
        "hum_maks" => $hum_maks,
        "hum_min" => $hum_min,
        "control_mode" => ($settings['control_mode'] === 'MANUAL' ? 1 : 0),
        "exhaust_control" => ($settings['exhaust_control'] === 'ON' ? 1 : 0),
        "wifi_ssid" => $settings['wifi_ssid'],
        "wifi_password" => $settings['wifi_password']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
