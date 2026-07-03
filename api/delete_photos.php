<?php
// delete_photos.php
// API untuk menghapus foto (semua atau beberapa terpilih) dari disk dan database

header("Content-Type: application/json");
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$ids = isset($_POST['ids']) ? $_POST['ids'] : [];

try {
    if ($action === 'all') {
        // Ambil semua file path untuk dihapus secara fisik
        $stmt = $pdo->query("SELECT filepath FROM camera_log");
        $photos = $stmt->fetchAll();
        
        foreach ($photos as $photo) {
            $filePathOnDisk = '../' . $photo['filepath'];
            if (file_exists($filePathOnDisk)) {
                unlink($filePathOnDisk);
            }
        }
        
        // Hapus semua log di database
        $pdo->exec("DELETE FROM camera_log");
        
        // Tambah notifikasi sistem
        addNotification('INFO', 'Semua foto galeri monitoring berhasil dihapus.');
        
        echo json_encode([
            "status" => "success",
            "message" => "Semua foto berhasil dihapus secara permanen."
        ]);
        exit;
    } 
    
    // Hapus foto yang dipilih saja
    if (!empty($ids) && is_array($ids)) {
        // Buat string placeholder: ?,?,?
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        // Ambil filepath dari database sebelum dihapus
        $stmt_select = $pdo->prepare("SELECT filepath FROM camera_log WHERE id IN ($placeholders)");
        $stmt_select->execute($ids);
        $photos = $stmt_select->fetchAll();
        
        foreach ($photos as $photo) {
            $filePathOnDisk = '../' . $photo['filepath'];
            if (file_exists($filePathOnDisk)) {
                unlink($filePathOnDisk);
            }
        }
        
        // Hapus data dari database
        $stmt_delete = $pdo->prepare("DELETE FROM camera_log WHERE id IN ($placeholders)");
        $stmt_delete->execute($ids);
        
        addNotification('INFO', count($ids) . ' foto galeri monitoring berhasil dihapus.');
        
        echo json_encode([
            "status" => "success",
            "message" => count($ids) . " foto terpilih berhasil dihapus."
        ]);
        exit;
    }
    
    echo json_encode(["status" => "error", "message" => "Aksi tidak dikenali atau tidak ada foto terpilih."]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>
