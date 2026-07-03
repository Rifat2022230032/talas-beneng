<?php
// save_settings.php
// API untuk memperbarui nilai pengaturan (suhu_maks, hum_maks, hum_min) di database

header("Content-Type: application/json");
require_once '../config.php';

// Memastikan request menggunakan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Gunakan POST."]);
    exit;
}

$suhu_maks = isset($_POST['suhu_maks']) ? (float)$_POST['suhu_maks'] : null;
$hum_maks = isset($_POST['hum_maks']) ? (float)$_POST['hum_maks'] : null;
$hum_min = isset($_POST['hum_min']) ? (float)$_POST['hum_min'] : null;

if ($suhu_maks === null || $hum_maks === null || $hum_min === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Semua parameter pengaturan harus diisi."]);
    exit;
}

// Validasi logika kelembapan (batas bawah tidak boleh melebihi batas atas)
if ($hum_min >= $hum_maks) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Kelembapan minimal harus lebih rendah dari kelembapan maksimal."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Persiapkan statement update
    $stmt = $pdo->prepare("INSERT INTO settings (key_name, value_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_val = ?");

    // Simpan masing-masing key
    $stmt->execute(['suhu_maks', $suhu_maks, $suhu_maks]);
    $stmt->execute(['hum_maks', $hum_maks, $hum_maks]);
    $stmt->execute(['hum_min', $hum_min, $hum_min]);

    $pdo->commit();

    // Log notifikasi perubahan pengaturan
    addNotification('INFO', "Pengaturan diperbarui: Suhu Maks = {$suhu_maks}°C, Hum Maks = {$hum_maks}%, Hum Min = {$hum_min}%.");

    echo json_encode([
        "status" => "success",
        "message" => "Pengaturan berhasil disimpan."
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
