<?php
// get_gallery_photos.php
// API untuk mengambil daftar foto dengan filter tanggal tertentu

header("Content-Type: application/json");
require_once '../config.php';

$date = isset($_GET['date']) ? $_GET['date'] : '';

try {
    if (!empty($date)) {
        // Cek format tanggal (YYYY-MM-DD)
        if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
            $stmt = $pdo->prepare("SELECT * FROM camera_log WHERE DATE(created_at) = ? ORDER BY id DESC");
            $stmt->execute([$date]);
        } else {
            throw new Exception("Format tanggal tidak valid.");
        }
    } else {
        // Ambil 100 foto terbaru jika filter kosong
        $stmt = $pdo->prepare("SELECT * FROM camera_log ORDER BY id DESC LIMIT 100");
        $stmt->execute();
    }
    
    $photos = $stmt->fetchAll();
    
    echo json_encode([
        "status" => "success",
        "data" => $photos
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
