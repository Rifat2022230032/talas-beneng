<?php
// camera_control.php
// API untuk mengontrol status kamera ESP32-CAM (ON/OFF) dari website

header("Content-Type: application/json");
require_once '../config.php';

// GET: Ambil status kamera saat ini
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $settings = getSystemSettings();
        $camera_status = isset($settings['camera_status']) ? $settings['camera_status'] : 'ON';
        echo json_encode([
            "status" => "success",
            "camera_status" => $camera_status
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// POST: Ubah status kamera
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $camera_status = isset($_POST['camera_status']) ? strtoupper(trim($_POST['camera_status'])) : null;

    if ($camera_status === null || ($camera_status !== 'ON' && $camera_status !== 'OFF')) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Parameter 'camera_status' harus 'ON' atau 'OFF'."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_val = ?");
        $stmt->execute(['camera_status', $camera_status, $camera_status]);

        $statusLabel = ($camera_status === 'ON') ? 'AKTIF (Mengambil Foto)' : 'BERHENTI (Stop Foto)';
        addNotification('INFO', "Status kamera ESP32-CAM diubah ke: " . $statusLabel);

        echo json_encode([
            "status" => "success",
            "camera_status" => $camera_status,
            "message" => "Status kamera berhasil diperbarui ke: " . $statusLabel
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Method lain tidak diizinkan
http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
?>
