<?php
// get_wifi_config.php
// API endpoint bagi ketiga perangkat ESP32 untuk mengambil konfigurasi WiFi terbaru secara sinkron

header("Content-Type: application/json");
require_once '../config.php';

try {
    $settings = getSystemSettings();
    echo json_encode([
        "status" => "success",
        "wifi_ssid" => $settings['wifi_ssid'],
        "wifi_password" => $settings['wifi_password']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
