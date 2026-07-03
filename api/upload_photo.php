<?php
// upload_photo.php
// API untuk menerima upload foto dari ESP32-CAM via HTTP POST Multipart/form-data

header("Content-Type: application/json");
require_once '../config.php';

// Memastikan request menggunakan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed. Gunakan POST Multipart/form-data."]);
    exit;
}

// Cek apakah file terkirim
if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'image' tidak ditemukan."]);
    // Log kegagalan ke notifikasi
    addNotification('ERROR', 'Kamera gagal upload: file tidak ditemukan dalam request.');
    exit;
}

$file = $_FILES['image'];

// Validasi error file upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal mengunggah file. Error code: " . $file['error']]);
    addNotification('ERROR', 'Kamera gagal upload: terjadi error pengiriman (Code ' . $file['error'] . ').');
    exit;
}

// Validasi jenis file (hanya JPEG/JPG)
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/pjpeg'];
$fileType = mime_content_type($file['tmp_name']);
if (!in_array($fileType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Hanya file JPG/JPEG yang diperbolehkan."]);
    addNotification('ERROR', 'Kamera gagal upload: format file tidak didukung (' . $fileType . ').');
    exit;
}

// Generate nama file otomatis berdasarkan YYYYMMDD_HHMMSS
date_default_timezone_set('Asia/Jakarta');
$filename = date('Ymd_His') . '.jpg';
$uploadDir = '../uploads/';
$targetPath = $uploadDir . $filename;

// Pindahkan file dari temp ke folder tujuan
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    try {
        // Path relatif untuk dibaca dari web frontend
        $relativeWebPath = 'uploads/' . $filename;

        // Simpan informasi file ke database
        $stmt = $pdo->prepare("INSERT INTO camera_log (filename, filepath) VALUES (?, ?)");
        $stmt->execute([$filename, $relativeWebPath]);

        // Simpan log notifikasi sukses upload
        addNotification('INFO', 'Kamera berhasil mengunggah foto terbaru: ' . $filename);

        $settings = getSystemSettings();
        echo json_encode([
            "status" => "uploaded",
            "wifi_ssid" => $settings['wifi_ssid'],
            "wifi_password" => $settings['wifi_password']
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan log foto ke database: " . $e->getMessage()]);
    }
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal memindahkan file ke folder uploads."]);
    addNotification('ERROR', 'Kamera gagal upload: kesalahan server saat menulis file ke disk.');
}
?>
