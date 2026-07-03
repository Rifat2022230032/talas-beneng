<?php
// get_settings.php
// API untuk mengambil nilai pengaturan (suhu_maks, hum_maks, hum_min) dalam format JSON

header("Content-Type: application/json");
require_once '../config.php';

try {
    $settings = getSystemSettings();
    echo json_encode([
        "status" => "success",
        "data" => $settings
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
