<?php
// setting.php
// Halaman Pengaturan Threshold Parameter Pengeringan
require_once 'config.php';

// Ambil nilai setting saat ini untuk render awal
$settings = getSystemSettings();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Parameter - Rumah Pengering</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom Style CSS -->
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
</head>
<body>

    <!-- Sidebar Menu -->
    <?php include 'sidebar.php'; ?>

    <!-- Wrapper Konten Utama -->
    <div class="main-wrapper">
        
        <!-- Header Navbar -->
        <?php include 'navbar.php'; ?>

        <!-- Judul Halaman -->
        <h3 class="fw-bold text-white mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-sliders text-info"></i> Pengaturan Threshold IoT
        </h3>

        <div class="row g-4">
            <!-- Kolom Form Pengaturan -->
            <div class="col-lg-6">
                <div class="glass-card">
                    <h5 class="fw-semibold text-white mb-4"><i class="bi bi-gear-fill"></i> Batas Sensor Kipas Exhaust</h5>
                    
                    <form id="settingsForm">
                        <!-- Input Suhu Maks -->
                        <div class="mb-4">
                            <label class="form-label text-muted d-flex justify-content-between">
                                <span>Suhu Maksimal Keamanan</span>
                                <span class="text-white fw-bold" id="labelSuhuMaks"><?php echo htmlspecialchars($settings['suhu_maks']); ?> °C</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-thermometer-high"></i></span>
                                <input type="number" step="0.1" class="form-control glass-input" id="inputSuhuMaks" name="suhu_maks" 
                                       value="<?php echo htmlspecialchars($settings['suhu_maks']); ?>" required>
                                <span class="input-group-text">°C</span>
                            </div>
                            <div class="form-text text-muted">Jika suhu di atas nilai ini, Kipas Exhaust akan mati paksa untuk pendinginan demi keamanan daun.</div>
                        </div>

                        <!-- Input Kelembapan Maks (Kipas ON) -->
                        <div class="mb-4">
                            <label class="form-label text-muted d-flex justify-content-between">
                                <span>Kelembapan Maksimal (Exhaust ON)</span>
                                <span class="text-white fw-bold" id="labelHumMaks"><?php echo htmlspecialchars($settings['hum_maks']); ?> %RH</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-droplet-fill"></i></span>
                                <input type="number" step="0.1" class="form-control glass-input" id="inputHumMaks" name="hum_maks" 
                                       value="<?php echo htmlspecialchars($settings['hum_maks']); ?>" required>
                                <span class="input-group-text">%RH</span>
                            </div>
                            <div class="form-text text-muted">Batas atas kelembapan udara. Jika kelembapan melebihi nilai ini, exhaust menyala untuk membuang uap air.</div>
                        </div>

                        <!-- Input Kelembapan Min (Kipas OFF) -->
                        <div class="mb-4">
                            <label class="form-label text-muted d-flex justify-content-between">
                                <span>Kelembapan Minimal / Target (Exhaust OFF)</span>
                                <span class="text-white fw-bold" id="labelHumMin"><?php echo htmlspecialchars($settings['hum_min']); ?> %RH</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-droplet"></i></span>
                                <input type="number" step="0.1" class="form-control glass-input" id="inputHumMin" name="hum_min" 
                                       value="<?php echo htmlspecialchars($settings['hum_min']); ?>" required>
                                <span class="input-group-text">%RH</span>
                            </div>
                            <div class="form-text text-muted">Target kelembapan kering. Jika kelembapan di bawah nilai ini, exhaust mati agar panas matahari tertahan di dalam.</div>
                        </div>

                        <!-- Tombol Simpan -->
                        <div class="d-grid gap-2 mt-4">
                            <button type="button" onclick="submitSettings()" class="btn btn-info py-2.5 fw-semibold"><i class="bi bi-check-circle"></i> Simpan Pengaturan</button>
                        </div>
                    </form>
                </div>

                <!-- PENGATURAN WIFI BERSAMA -->
                <div class="glass-card mt-4">
                    <h5 class="fw-semibold text-white mb-3"><i class="bi bi-wifi text-info"></i> Pengaturan WiFi Bersama</h5>
                    
                    <!-- Alert status konektivitas perangkat -->
                    <div id="wifiStatusAlert" class="alert alert-warning py-2.5 px-3 mb-4 rounded-3 d-flex align-items-center gap-2" style="font-size: 0.85rem; border: 1px solid rgba(255, 193, 7, 0.2); background: rgba(255, 193, 7, 0.05); color: #ffc107; transition: all 0.3s ease;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span id="wifiStatusAlertText">Memeriksa status perangkat IoT dan Kamera...</span>
                    </div>

                    <form id="wifiForm">
                        <!-- Input SSID -->
                        <div class="mb-3">
                            <label class="form-label text-muted d-flex justify-content-between">
                                <span>SSID WiFi</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-router"></i></span>
                                <input type="text" class="form-control glass-input" id="inputWifiSSID" name="wifi_ssid" 
                                       value="<?php echo htmlspecialchars($settings['wifi_ssid']); ?>" required disabled>
                            </div>
                        </div>

                        <!-- Input Password -->
                        <div class="mb-4">
                            <label class="form-label text-muted d-flex justify-content-between">
                                <span>Password WiFi</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="password" class="form-control glass-input" id="inputWifiPassword" name="wifi_password" 
                                       value="<?php echo htmlspecialchars($settings['wifi_password']); ?>" required disabled>
                                <button class="btn btn-outline-secondary" type="button" id="btnToggleWifiPass" onclick="toggleWifiPasswordVisibility()" style="border: 1px solid var(--border-glass);"><i class="bi bi-eye-slash"></i></button>
                            </div>
                        </div>

                        <!-- Tombol Simpan WiFi -->
                        <div class="d-grid gap-2">
                            <button type="button" id="btnSaveWifi" onclick="submitWifiSettings()" class="btn btn-outline-secondary py-2.5 fw-semibold text-muted" disabled><i class="bi bi-wifi"></i> Simpan Konfigurasi WiFi</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Kolom Panduan Parameter -->
            <div class="col-lg-6">
                <div class="glass-card h-100">
                    <h5 class="fw-semibold mb-3 text-info"><i class="bi bi-info-circle-fill"></i> Panduan Pengaturan Pengeringan</h5>
                    <p class="small text-muted mb-4">Untuk menghasilkan rajangan daun Talas Beneng kualitas ekspor dengan warna kuning keemasan yang konsisten, disarankan mengikuti panduan berikut:</p>
                    
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex gap-3">
                            <div class="p-2.5 bg-danger bg-opacity-15 text-danger rounded-3 align-self-start">
                                <i class="bi bi-thermometer-high fs-5"></i>
                            </div>
                            <div>
                                <h6 class="m-0 fw-semibold">Suhu Maksimal (Safety)</h6>
                                <p class="small text-muted m-0">Suhu aman berkisar antara 45°C - 50°C. Suhu melebihi 50°C berisiko merusak warna kuning keemasan daun menjadi kecokelatan.</p>
                            </div>
                        </div>

                        <div class="d-flex gap-3">
                            <div class="p-2.5 bg-primary bg-opacity-15 text-primary rounded-3 align-self-start">
                                <i class="bi bi-droplet-fill fs-5"></i>
                            </div>
                            <div>
                                <h6 class="m-0 fw-semibold">Batas Atas Kelembapan (60%)</h6>
                                <p class="small text-muted m-0">Batas atas sirkulasi. Jika udara ruangan terlalu jenuh (>60%), penguapan air daun melambat dan berisiko muncul jamur/hitam.</p>
                            </div>
                        </div>

                        <div class="d-flex gap-3">
                            <div class="p-2.5 bg-success bg-opacity-15 text-success rounded-3 align-self-start">
                                <i class="bi bi-droplet fs-5"></i>
                            </div>
                            <div>
                                <h6 class="m-0 fw-semibold">Batas Bawah Kelembapan (50%)</h6>
                                <p class="small text-muted m-0">Batas bawah target kering. Jika kelembapan di dalam dome sudah di bawah 50%, exhaust dimatikan agar udara hangat tertahan di dalam kubah.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Script JavaScript Bootstrap, SweetAlert2, and AJAX -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/main.js?v=<?php echo filemtime('js/main.js'); ?>"></script>

    <script>
    // Menyimpan Pengaturan menggunakan AJAX POST ke API
    function submitSettings() {
        const suhuMaks = parseFloat(document.getElementById('inputSuhuMaks').value);
        const humMaks = parseFloat(document.getElementById('inputHumMaks').value);
        const humMin = parseFloat(document.getElementById('inputHumMin').value);

        // Validasi logika kelembapan
        if (humMin >= humMaks) {
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'Kelembapan minimal (Exhaust OFF) harus lebih rendah dari kelembapan maksimal (Exhaust ON)!',
                background: '#0b101e',
                color: '#fff',
                confirmButtonColor: '#00d2ff'
            });
            return;
        }

        // Tampilkan konfirmasi
        Swal.fire({
            title: 'Simpan Pengaturan?',
            text: "Perubahan ini akan dikirimkan otomatis ke perangkat ESP32 pada siklus berikutnya.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#00d2ff',
            cancelButtonColor: '#ff3c6a',
            confirmButtonText: 'Ya, Simpan!',
            cancelButtonText: 'Batal',
            background: '#0b101e',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                // Buat Form Data POST
                const formData = new FormData();
                formData.append('suhu_maks', suhuMaks);
                formData.append('hum_maks', humMaks);
                formData.append('hum_min', humMin);

                // Kirim AJAX POST
                fetch('api/save_settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        // Perbarui label text secara realtime
                        document.getElementById('labelSuhuMaks').innerText = suhuMaks.toFixed(1) + ' °C';
                        document.getElementById('labelHumMaks').innerText = humMaks.toFixed(1) + ' %RH';
                        document.getElementById('labelHumMin').innerText = humMin.toFixed(1) + ' %RH';

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Disimpan',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false,
                            background: '#0b101e',
                            color: '#fff'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan',
                            text: response.message,
                            background: '#0b101e',
                            color: '#fff',
                            confirmButtonColor: '#00d2ff'
                        });
                    }
                })
                .catch(err => {
                    console.error("Error saving settings:", err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Koneksi',
                        text: 'Terjadi kegagalan komunikasi dengan server.',
                        background: '#0b101e',
                        color: '#fff',
                        confirmButtonColor: '#00d2ff'
                    });
                });
            }
        });
    }

    // Toggle visibilitas Password WiFi
    function toggleWifiPasswordVisibility() {
        const passInput = document.getElementById('inputWifiPassword');
        const passIcon = document.querySelector('#btnToggleWifiPass i');
        if (passInput.type === 'password') {
            passInput.type = 'text';
            passIcon.className = 'bi bi-eye';
        } else {
            passInput.type = 'password';
            passIcon.className = 'bi bi-eye-slash';
        }
    }

    // Fungsi pemantauan status online perangkat IoT & Kamera secara realtime
    function monitorDeviceStatusForWifi() {
        const espText = document.getElementById('espStatusText');
        const camText = document.getElementById('camStatusText');
        const statusAlert = document.getElementById('wifiStatusAlert');
        const statusAlertText = document.getElementById('wifiStatusAlertText');
        const ssidInput = document.getElementById('inputWifiSSID');
        const passInput = document.getElementById('inputWifiPassword');
        const saveBtn = document.getElementById('btnSaveWifi');

        if (!espText || !camText || !statusAlert) return;

        const espOnline = espText.innerText.trim() === 'Online';
        const camOnline = camText.innerText.trim() === 'Online';

        if (espOnline && camOnline) {
            // Kedua perangkat online
            statusAlert.className = "alert alert-success py-2.5 px-3 mb-4 rounded-3 d-flex align-items-center gap-2";
            statusAlert.style.border = "1px solid rgba(25, 135, 84, 0.2)";
            statusAlert.style.background = "rgba(25, 135, 84, 0.05)";
            statusAlert.style.color = "#198754";
            statusAlertText.innerHTML = '<i class="bi bi-check-circle-fill"></i> Kedua perangkat (IoT & Kamera) Online. Pengaturan WiFi dapat diubah.';
            
            ssidInput.removeAttribute('disabled');
            passInput.removeAttribute('disabled');
            saveBtn.removeAttribute('disabled');
            saveBtn.className = "btn btn-info py-2.5 fw-semibold";
        } else {
            // Salah satu atau kedua offline
            statusAlert.className = "alert alert-danger py-2.5 px-3 mb-4 rounded-3 d-flex align-items-center gap-2";
            statusAlert.style.border = "1px solid rgba(220, 53, 69, 0.2)";
            statusAlert.style.background = "rgba(220, 53, 69, 0.05)";
            statusAlert.style.color = "#dc3545";
            statusAlertText.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> SSID dan Password tidak dapat diubah karena perangkat IoT atau kamera sedang offline.';
            
            ssidInput.setAttribute('disabled', 'true');
            passInput.setAttribute('disabled', 'true');
            saveBtn.setAttribute('disabled', 'true');
            saveBtn.className = "btn btn-outline-secondary py-2.5 fw-semibold text-muted";
        }
    }

    // Menyimpan Pengaturan WiFi Bersama
    function submitWifiSettings() {
        const ssid = document.getElementById('inputWifiSSID').value.trim();
        const password = document.getElementById('inputWifiPassword').value.trim();

        if (!ssid) {
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: 'SSID WiFi tidak boleh kosong!',
                background: '#0b101e',
                color: '#fff',
                confirmButtonColor: '#00d2ff'
            });
            return;
        }

        Swal.fire({
            title: 'Ubah WiFi Perangkat?',
            text: "Perangkat IoT (Sensor, Kamera, Display) akan diperbarui secara sinkron dan otomatis berpindah ke jaringan WiFi baru ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00d2ff',
            cancelButtonColor: '#ff3c6a',
            confirmButtonText: 'Ya, Ubah WiFi!',
            cancelButtonText: 'Batal',
            background: '#0b101e',
            color: '#fff'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('wifi_ssid', ssid);
                formData.append('wifi_password', password);

                fetch('api/save_wifi.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'WiFi Diperbarui',
                            text: response.message,
                            background: '#0b101e',
                            color: '#fff',
                            confirmButtonColor: '#00d2ff'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan',
                            text: response.message,
                            background: '#0b101e',
                            color: '#fff',
                            confirmButtonColor: '#00d2ff'
                        });
                    }
                })
                .catch(err => {
                    console.error("Error saving WiFi:", err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Koneksi',
                        text: 'Terjadi kegagalan komunikasi dengan server.',
                        background: '#0b101e',
                        color: '#fff',
                        confirmButtonColor: '#00d2ff'
                    });
                });
            }
        });
    }

    // Jalankan monitoring status perangkat untuk tombol WiFi pertama kali & berkala
    document.addEventListener('DOMContentLoaded', function() {
        monitorDeviceStatusForWifi();
        setInterval(monitorDeviceStatusForWifi, 1500);
    });
    </script>
</body>
</html>
