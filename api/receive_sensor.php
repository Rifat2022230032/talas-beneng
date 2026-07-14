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

// Mengambil data exhaust per channel (dengan fallback ke nilai exhaust tunggal)
$exhaust1 = isset($_POST['exhaust1']) ? (int)$_POST['exhaust1'] : ($exhaust !== null ? $exhaust : 0);
$exhaust2 = isset($_POST['exhaust2']) ? (int)$_POST['exhaust2'] : ($exhaust !== null ? $exhaust : 0);
$exhaust3 = isset($_POST['exhaust3']) ? (int)$_POST['exhaust3'] : ($exhaust !== null ? $exhaust : 0);
$exhaust4 = isset($_POST['exhaust4']) ? (int)$_POST['exhaust4'] : ($exhaust !== null ? $exhaust : 0);

// Jika exhaust tunggal tidak ada tapi exhaust1-4 ada, set exhaust tunggal ke OR dari keempatnya
if ($exhaust === null && (isset($_POST['exhaust1']) || isset($_POST['exhaust2']) || isset($_POST['exhaust3']) || isset($_POST['exhaust4']))) {
    $exhaust = ($exhaust1 || $exhaust2 || $exhaust3 || $exhaust4) ? 1 : 0;
}

// Validasi data input
if ($temperature === null || $humidity === null || $sht_temperature === null || $exhaust === null || $wifi === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data input tidak lengkap."]);
    exit;
}

try {
    // Cek apakah ada pembaruan settings dari perangkat (display)
    $update_settings = isset($_POST['update_settings']) ? (int)$_POST['update_settings'] : 0;
    if ($update_settings === 1) {
        $posted_mode = isset($_POST['control_mode']) ? (int)$_POST['control_mode'] : null;
        if ($posted_mode !== null) {
            $mode_str = ($posted_mode === 1) ? 'MANUAL' : 'AUTO';
            $stmt_update = $pdo->prepare("INSERT INTO settings (key_name, value_val) VALUES ('control_mode', ?) ON DUPLICATE KEY UPDATE value_val = ?");
            $stmt_update->execute([$mode_str, $mode_str]);
            addNotification('INFO', "Mode kontrol sistem diubah ke: " . $mode_str . " via Display");
        }

        if ($posted_mode === 1) {
            // Update status manual exhaust per channel ke settings
            for ($i = 1; $i <= 4; $i++) {
                $param_name = "exhaust{$i}";
                if (isset($_POST[$param_name])) {
                    $state_val = ((int)$_POST[$param_name] === 1) ? 'ON' : 'OFF';
                    $key_name = "exhaust_{$i}_control";
                    $stmt_update = $pdo->prepare("INSERT INTO settings (key_name, value_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_val = ?");
                    $stmt_update->execute([$key_name, $state_val, $state_val]);
                    
                    if ($i === 1) {
                        $stmt_update->execute(['exhaust_control', $state_val, $state_val]);
                    }
                    addNotification('INFO', "Kipas Exhaust $i manual diubah ke: " . $state_val . " via Display");
                }
            }
        }
    }

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
    $stmt_last = $pdo->query("SELECT exhaust, exhaust_1, exhaust_2, exhaust_3, exhaust_4 FROM sensor_log ORDER BY id DESC LIMIT 1");
    $last_log = $stmt_last->fetch();
    if ($last_log) {
        $last_exhaust = isset($last_log['exhaust']) ? (int)$last_log['exhaust'] : 0;
        if ($last_exhaust == 0 && $exhaust == 1) {
            addNotification('SUCCESS', 'Kipas Exhaust Utama dinyalakan (ON).');
        } elseif ($last_exhaust == 1 && $exhaust == 0) {
            addNotification('INFO', 'Kipas Exhaust Utama dimatikan (OFF).');
        }

        // Notifikasi per channel
        for ($i = 1; $i <= 4; $i++) {
            $last_state = isset($last_log["exhaust_$i"]) ? (int)$last_log["exhaust_$i"] : 0;
            $curr_state = (int)${"exhaust$i"};
            if ($last_state == 0 && $curr_state == 1) {
                addNotification('SUCCESS', "Kipas Exhaust $i dinyalakan (ON).");
            } elseif ($last_state == 1 && $curr_state == 0) {
                addNotification('INFO', "Kipas Exhaust $i dimatikan (OFF).");
            }
        }
    }

    // 5. Simpan data sensor ke tabel sensor_log
    $stmt = $pdo->prepare("INSERT INTO sensor_log (temperature, humidity, sht_temperature, exhaust, exhaust_1, exhaust_2, exhaust_3, exhaust_4, wifi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$temperature, $humidity, $sht_temperature, $exhaust, $exhaust1, $exhaust2, $exhaust3, $exhaust4, $wifi]);

    // Respon sukses dengan mengirimkan konfigurasi terbaru agar dibaca oleh ESP32
    echo json_encode([
        "status" => "success",
        "suhu_maks" => $suhu_maks,
        "hum_maks" => $hum_maks,
        "hum_min" => $hum_min,
        "control_mode" => ($settings['control_mode'] === 'MANUAL' ? 1 : 0),
        "exhaust_control" => ($settings['exhaust_control'] === 'ON' ? 1 : 0),
        "exhaust_1_control" => ($settings['exhaust_1_control'] === 'ON' ? 1 : 0),
        "exhaust_2_control" => ($settings['exhaust_2_control'] === 'ON' ? 1 : 0),
        "exhaust_3_control" => ($settings['exhaust_3_control'] === 'ON' ? 1 : 0),
        "exhaust_4_control" => ($settings['exhaust_4_control'] === 'ON' ? 1 : 0),
        "wifi_ssid" => $settings['wifi_ssid'],
        "wifi_password" => $settings['wifi_password']
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
