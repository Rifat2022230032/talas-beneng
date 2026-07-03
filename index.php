<?php
// index.php
// Halaman Utama / Landing Page Rumah Pengering Daun Talas Beneng
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Monitoring Rumah Pengering Daun Talas Beneng</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Style CSS -->
    <link rel="stylesheet" href="css/style.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body>

    <!-- Sidebar Menu -->
    <?php include 'sidebar.php'; ?>

    <!-- Wrapper Konten Utama -->
    <div class="main-wrapper">
        
        <!-- Header Navbar -->
        <?php include 'navbar.php'; ?>

        <!-- Konten Halaman -->
        <div class="row g-4 align-items-center mb-5">
            <!-- Kolom Teks Informasi -->
            <div class="col-lg-7">
                <div class="glass-card p-4 p-md-5">
                    <span class="badge bg-info text-dark mb-3 px-3 py-2 fw-semibold rounded-pill">Sistem Cerdas Solar Dome</span>
                    <h1 class="display-5 fw-bold mb-3" style="background: linear-gradient(135deg, #0f172a 30%, #475569); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                        Rumah Pengering Daun Talas Beneng
                    </h1>
                    <p class="lead text-secondary-emphasis mb-4" style="line-height: 1.7; color: var(--text-muted) !important;">
                        Website monitoring cerdas untuk mengoptimalkan proses pengeringan daun Talas Beneng. Menggunakan energi panas matahari (Solar Dome) untuk pemanasan alami dan sirkulasi pembuangan uap air terkendali berbasis kelembapan udara otomatis.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="dashboard.php" class="btn btn-info px-4 py-3 fw-semibold rounded-3 shadow-lg d-inline-flex align-items-center gap-2">
                            <i class="bi bi-speedometer2"></i> Masuk Dashboard Monitoring
                        </a>
                        <a href="setting.php" class="btn btn-outline-dark px-4 py-3 fw-semibold rounded-3 d-inline-flex align-items-center gap-2">
                            <i class="bi bi-sliders"></i> Atur Batas Sensor
                        </a>
                    </div>
                </div>
            </div>

            <!-- Ilustrasi / Logo Sistem -->
            <div class="col-lg-5 text-center">
                <div class="glass-card py-5 shadow-lg d-flex flex-column align-items-center justify-content-center" style="min-height: 380px;">
                    <div class="position-relative mb-4">
                        <i class="bi bi-cloud-sun text-info" style="font-size: 8rem; filter: drop-shadow(0 0 20px rgba(0, 210, 255, 0.4));"></i>
                    </div>
                    <h3 class="fw-bold m-0 text-white">Smart Drying IoT</h3>
                    <p class="text-muted mt-2 px-4 small">Memonitor sirkulasi udara lembap secara cerdas untuk menghasilkan produk rajangan daun talas beneng kualitas ekspor.</p>
                </div>
            </div>
        </div>

        <!-- Ringkasan Data Terbaru Realtime -->
        <h3 class="mb-4 fw-semibold text-white d-flex align-items-center gap-2">
            <i class="bi bi-activity text-info"></i> Ringkasan Data Realtime
        </h3>
        
        <div class="row g-4">
            <!-- Card Suhu Tertinggi -->
            <div class="col-md-4">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold small">Suhu Pengering</span>
                            <div class="widget-val" id="homeTemp">-<span class="widget-unit">°C</span></div>
                        </div>
                        <div class="p-3 bg-info bg-opacity-10 text-info rounded-3">
                            <i class="bi bi-thermometer-half fs-2"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-top border-light border-opacity-10 d-flex justify-content-between small text-muted">
                        <span>SHT31 Sensor</span>
                        <span id="homeShtTemp">- °C</span>
                    </div>
                </div>
            </div>

            <!-- Card Kelembapan -->
            <div class="col-md-4">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold small">Kelembapan Udara</span>
                            <div class="widget-val" id="homeHum">-<span class="widget-unit">%RH</span></div>
                        </div>
                        <div class="p-3 bg-primary bg-opacity-20 text-info rounded-3">
                            <i class="bi bi-moisture fs-2"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-top border-light border-opacity-10 d-flex justify-content-between small text-muted">
                        <span>Target Batas</span>
                        <span id="homeHumBatas">-</span>
                    </div>
                </div>
            </div>

            <!-- Card Status Exhaust -->
            <div class="col-md-4">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted text-uppercase fw-semibold small">Status Kipas Exhaust</span>
                            <h2 class="mt-2 fw-bold" id="homeExhaustText">-</h2>
                        </div>
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-3" id="homeExhaustIconContainer">
                            <i class="bi bi-fan fs-2" id="homeExhaustIcon"></i>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-top border-light border-opacity-10 d-flex justify-content-between small text-muted">
                        <span>Metode Kontrol</span>
                        <span id="homeModeText">-</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Script JavaScript Bootstrap, Jquery, Sweetalert2, and AJAX -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/main.js"></script>

    <script>
    // AJAX Fetch Data Terkini untuk Ringkasan Halaman Home
    function loadHomeRealtimeData() {
        fetch('api/get_latest_sensor.php')
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    const data = result.data;
                    
                    // Update Suhu & Kelembapan
                    document.getElementById('homeTemp').innerHTML = data.temperature.toFixed(1) + '<span class="widget-unit">°C</span>';
                    document.getElementById('homeShtTemp').innerText = data.sht_temperature.toFixed(1) + ' °C';
                    document.getElementById('homeHum').innerHTML = data.humidity.toFixed(1) + '<span class="widget-unit">%RH</span>';
                    document.getElementById('homeHumBatas').innerText = data.thresholds.hum_min + '% - ' + data.thresholds.hum_maks + '%';

                    // Update Status Kipas Exhaust
                    const exhaustText = document.getElementById('homeExhaustText');
                    const exhaustIcon = document.getElementById('homeExhaustIcon');
                    const exhaustContainer = document.getElementById('homeExhaustIconContainer');
                    
                    if (data.exhaust === 1) {
                        exhaustText.innerText = 'MENYALA (ON)';
                        exhaustText.className = 'mt-2 fw-bold text-success';
                        exhaustIcon.className = 'bi bi-fan fs-2 spin-animation';
                        exhaustContainer.className = 'p-3 bg-success bg-opacity-20 text-success rounded-3';
                    } else {
                        exhaustText.innerText = 'MATI (OFF)';
                        exhaustText.className = 'mt-2 fw-bold text-danger';
                        exhaustIcon.className = 'bi bi-fan fs-2';
                        exhaustContainer.className = 'p-3 bg-danger bg-opacity-20 text-danger rounded-3';
                    }

                    // Update Mode
                    document.getElementById('homeModeText').innerText = data.mode === 1 ? 'MANUAL (Display)' : 'OTOMATIS (Kelembapan)';
                }
            })
            .catch(err => {
                console.error("Gagal memuat data ringkasan home:", err);
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadHomeRealtimeData();
        // Refresh data ringkasan setiap 2 detik
        setInterval(loadHomeRealtimeData, 2000);
    });
    </script>
    
    <style>
    /* CSS Khusus Animasi Kipas berputar */
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin-animation {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    </style>
</body>
</html>
