<?php
// config.php
// Konfigurasi Database dan Helper Function untuk IoT Rumah Pengering

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_rumah_pengering');

// Pastikan session aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Koneksi PDO ke Database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

/**
 * Fungsi untuk menyimpan log notifikasi baru ke database.
 * 
 * @param string $type Jenis notifikasi (misal: INFO, WARNING, ERROR, SUCCESS)
 * @param string $message Detail pesan notifikasi
 * @return bool True jika berhasil, False jika gagal
 */
function addNotification($type, $message) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notification_log (type, message, status) VALUES (?, ?, 'unread')");
        return $stmt->execute([$type, $message]);
    } catch (PDOException $e) {
        error_log("Gagal menyimpan notifikasi: " . $e->getMessage());
        return false;
    }
}

/**
 * Fungsi untuk mengambil seluruh setting/parameter dari database.
 * 
 * @return array Asosiatif key => value setting
 */
function getSystemSettings() {
    global $pdo;
    $settings = [
        'suhu_maks' => 50.0, // Default fallback
        'hum_maks' => 60.0,
        'hum_min' => 50.0,
        'control_mode' => 'AUTO',
        'exhaust_control' => 'OFF',
        'wifi_ssid' => 'Kalyca',
        'wifi_password' => 'Athifacantik'
    ];
    try {
        $stmt = $pdo->query("SELECT key_name, value_val FROM settings");
        $db_keys = [];
        while ($row = $stmt->fetch()) {
            $key = $row['key_name'];
            $val = $row['value_val'];
            $db_keys[] = $key;
            if ($key === 'control_mode' || $key === 'exhaust_control' || $key === 'wifi_ssid' || $key === 'wifi_password') {
                $settings[$key] = $val;
            } else {
                $settings[$key] = (float)$val;
            }
        }

        // Auto-seed missing keys dynamically in the database
        $missing_keys = array_diff(array_keys($settings), $db_keys);
        if (!empty($missing_keys)) {
            $insert_stmt = $pdo->prepare("INSERT INTO settings (key_name, value_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_val = ?");
            foreach ($missing_keys as $key) {
                $insert_stmt->execute([$key, (string)$settings[$key], (string)$settings[$key]]);
            }
        }
    } catch (PDOException $e) {
        error_log("Gagal mengambil settings: " . $e->getMessage());
    }
    return $settings;
}
?>
