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

// Support individual exhaust controls
$exhaust_1_control = isset($_POST['exhaust_1_control']) ? $_POST['exhaust_1_control'] : null;
$exhaust_2_control = isset($_POST['exhaust_2_control']) ? $_POST['exhaust_2_control'] : null;
$exhaust_3_control = isset($_POST['exhaust_3_control']) ? $_POST['exhaust_3_control'] : null;
$exhaust_4_control = isset($_POST['exhaust_4_control']) ? $_POST['exhaust_4_control'] : null;

// Support single channel parameters (e.g. exhaust_id=1&state=ON)
$exhaust_id = isset($_POST['exhaust_id']) ? (int)$_POST['exhaust_id'] : null;
$exhaust_state = isset($_POST['state']) ? $_POST['state'] : null;

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
        // Also sync exhaust_1_control for fallback compatibility
        $stmt->execute(['exhaust_1_control', $exhaust_control, $exhaust_control]);
        addNotification('INFO', "Kipas Exhaust manual diubah ke: " . $exhaust_control);
    }

    // Process exhaust_1_control to exhaust_4_control parameters
    for ($i = 1; $i <= 4; $i++) {
        $param_name = "exhaust_{$i}_control";
        $param_val = ${$param_name};
        if ($param_val !== null) {
            if ($param_val !== 'ON' && $param_val !== 'OFF') {
                throw new Exception("Invalid exhaust $i control state.");
            }
            $stmt->execute([$param_name, $param_val, $param_val]);
            addNotification('INFO', "Kipas Exhaust $i manual diubah ke: " . $param_val);
        }
    }

    // Process consolidated parameters
    if ($exhaust_id !== null && $exhaust_state !== null) {
        if ($exhaust_id < 1 || $exhaust_id > 4) {
            throw new Exception("Invalid exhaust ID.");
        }
        if ($exhaust_state !== 'ON' && $exhaust_state !== 'OFF') {
            throw new Exception("Invalid exhaust state.");
        }
        $key = "exhaust_{$exhaust_id}_control";
        $stmt->execute([$key, $exhaust_state, $exhaust_state]);
        addNotification('INFO', "Kipas Exhaust $exhaust_id manual diubah ke: " . $exhaust_state);
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
