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
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
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
                <div class="glass-card py-5 shadow-lg d-flex flex-column align-items-center justify-content-center" style="min-height: 380px; border-top: 4px solid var(--primary-leaf);">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 150" width="160" height="160" class="leaf-glow" style="margin-bottom: 1.5rem;">
                        <!-- Solar Dome Arc -->
                        <path d="M15,120 A60,60 0 0,1 135,120" fill="none" stroke="var(--primary-leaf)" stroke-width="3" stroke-dasharray="6,4" opacity="0.6"/>
                        <line x1="10" y1="120" x2="140" y2="120" stroke="var(--accent-brown)" stroke-width="4" stroke-linecap="round"/>
                        
                        <!-- Sun -->
                        <circle cx="120" cy="35" r="14" fill="#ffb74d" opacity="0.9"/>
                        <path d="M120,12 L120,20 M120,50 L120,58 M97,35 L105,35 M135,35 L143,35 M104,19 L110,25 M130,45 L136,51 M104,51 L110,45 M130,19 L136,25" stroke="#ffa726" stroke-width="2" stroke-linecap="round"/>

                        <!-- Talas Leaf -->
                        <g transform="translate(45, 45)">
                            <path d="M30,5 C30,5 58,30 54,58 C50,76 30,82 30,82 C30,82 10,76 6,58 C2,30 30,5 30,5 Z" fill="var(--primary-leaf)" stroke="var(--primary-leaf-dark)" stroke-width="3" stroke-linejoin="round"/>
                            <path d="M30,5 L30,82" stroke="var(--primary-leaf-dark)" stroke-width="2.5" stroke-linecap="round"/>
                            <path d="M30,28 Q43,24 48,20" stroke="var(--primary-leaf-dark)" stroke-width="2" stroke-linecap="round" fill="none"/>
                            <path d="M30,28 Q17,24 12,20" stroke="var(--primary-leaf-dark)" stroke-width="2" stroke-linecap="round" fill="none"/>
                            <path d="M30,46 Q45,41 49,37" stroke="var(--primary-leaf-dark)" stroke-width="2" stroke-linecap="round" fill="none"/>
                            <path d="M30,46 Q15,41 11,37" stroke="var(--primary-leaf-dark)" stroke-width="2" stroke-linecap="round" fill="none"/>
                            <path d="M30,62 Q45,58 48,54" stroke="var(--primary-leaf-dark)" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                            <path d="M30,62 Q15,58 12,54" stroke="var(--primary-leaf-dark)" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                        </g>
                        
                        <!-- Wind / Air flow -->
                        <path d="M25,85 Q40,75 50,85 T75,85" fill="none" stroke="var(--neon-blue)" stroke-width="2" stroke-linecap="round" opacity="0.7"/>
                        <path d="M80,95 Q95,85 105,95 T130,95" fill="none" stroke="var(--neon-blue)" stroke-width="2" stroke-linecap="round" opacity="0.7"/>
                    </svg>
                    <h3 class="fw-bold m-0 text-white" style="color: var(--primary-leaf-dark) !important;">Smart Drying IoT</h3>
                    <p class="text-muted mt-2 px-4 small" style="color: var(--text-muted) !important;">Memonitor sirkulasi udara lembap secara cerdas untuk menghasilkan produk rajangan daun talas beneng kualitas ekspor.</p>
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
                    <div class="d-flex justify-content-between align-items-start w-100">
                        <div class="w-100">
                            <span class="text-muted text-uppercase fw-semibold small">Status Kipas Exhaust</span>
                            <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-1" id="homeExhaustList">
                                <?php for($i = 1; $i <= 4; $i++): ?>
                                <div class="d-flex flex-column align-items-center p-2 rounded bg-light bg-opacity-5 border border-light border-opacity-10" style="min-width: 58px; flex: 1;">
                                    <span class="text-muted mb-1" style="font-size: 0.7rem; font-weight: 600;">EXH <?= $i ?></span>
                                    <i class="bi bi-fan fs-4 text-secondary mb-1" id="homeExhaustIcon<?= $i ?>"></i>
                                    <span class="badge bg-secondary px-1.5 py-0.5 rounded-pill" style="font-size: 0.6rem;" id="homeExhaustText<?= $i ?>">OFF</span>
                                </div>
                                <?php endfor; ?>
                            </div>
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
    <script src="js/main.js?v=<?php echo filemtime('js/main.js'); ?>"></script>

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

                    // Update Status Kipas Exhaust (4 Channels)
                    for (let i = 1; i <= 4; i++) {
                        const state = data[`exhaust_${i}`] ?? 0;
                        const icon = document.getElementById(`homeExhaustIcon${i}`);
                        const text = document.getElementById(`homeExhaustText${i}`);
                        if (state === 1) {
                            text.innerText = 'ON';
                            text.className = 'badge bg-success text-dark px-1.5 py-0.5 rounded-pill';
                            icon.className = 'bi bi-fan fs-4 text-success spin-animation';
                        } else {
                            text.innerText = 'OFF';
                            text.className = 'badge bg-danger text-white px-1.5 py-0.5 rounded-pill';
                            icon.className = 'bi bi-fan fs-4 text-danger';
                        }
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
