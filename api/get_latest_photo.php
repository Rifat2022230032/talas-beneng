<?php
// get_latest_photo.php
// API untuk mengambil data foto kamera terbaru dari database

header("Content-Type: application/json");
require_once '../config.php';

try {
    $stmt = $pdo->query("SELECT * FROM camera_log ORDER BY id DESC LIMIT 1");
    $latest = $stmt->fetch();

    if ($latest) {
        // Hitung selisih waktu untuk status online (batas 30 detik)
        date_default_timezone_set('Asia/Jakarta');
        $last_update = strtotime($latest['created_at']);
        $now = time();
        $diff = $now - $last_update;
        $cam_status = ($diff <= 30) ? 'Online' : 'Offline';

        echo json_encode([
            "status" => "success",
            "data" => [
                "id" => $latest['id'],
                "filename" => $latest['filename'],
                "filepath" => $latest['filepath'],
                "created_at" => $latest['created_at'],
                "time_ago" => $diff . " detik lalu",
                "cam_status" => $cam_status
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Belum ada foto terunggah di database.",
            "data" => [
                "cam_status" => "Offline"
            ]
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
