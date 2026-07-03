<?php
// save_control.php
// API untuk mengubah control_mode dan exhaust_control dari website

header("Content-Type: application/json");
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

$control_mode = isset($_POST['control_mode']) ? $_POST['control_mode'] : null;
$exhaust_control = isset($_POST['exhaust_control']) ? $_POST['exhaust_control'] : null;

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO settings (key_name, value_val) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_val = ?");

    if ($control_mode !== null) {
        if ($control_mode !== 'AUTO' && $control_mode !== 'MANUAL') {
            throw new Exception("Invalid control mode.");
        }
        $stmt->execute(['control_mode', $control_mode, $control_mode]);
        addNotification('INFO', "Mode kontrol sistem diubah ke: " . $control_mode);
    }

    if ($exhaust_control !== null) {
        if ($exhaust_control !== 'ON' && $exhaust_control !== 'OFF') {
            throw new Exception("Invalid exhaust control state.");
        }
        $stmt->execute(['exhaust_control', $exhaust_control, $exhaust_control]);
        addNotification('INFO', "Kipas Exhaust manual diubah ke: " . $exhaust_control);
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Kontrol berhasil diperbarui."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
