<?php
// dashboard.php
// Halaman Dashboard Monitoring Utama Realtime dengan Grafik
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring - Rumah Pengering</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-white m-0 d-flex align-items-center gap-2">
                <i class="bi bi-speedometer2 text-info"></i> Realtime Dashboard
            </h3>
            <span class="text-muted small">Update terakhir: <strong id="lastUpdateTime" class="text-white">-</strong></span>
        </div>

        <!-- Widget Telemetri Sensor -->
        <div class="row g-4 mb-4">
            <!-- Widget 1: Suhu DS18B20 -->
            <div class="col-xl-3 col-md-6">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Suhu DS18B20</span>
                            <div class="widget-val" id="tempDS">-<span class="widget-unit">°C</span></div>
                        </div>
                        <div class="p-3 rounded-3 d-flex align-items-center justify-content-center" style="background: rgba(198, 40, 40, 0.08) !important; color: var(--neon-red) !important; border: 1px solid rgba(198, 40, 40, 0.15); width: 50px; height: 50px;">
                            <i class="bi bi-thermometer-high fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 2: Kelembaban SHT31 -->
            <div class="col-xl-3 col-md-6">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Kelembapan SHT31</span>
                            <div class="widget-val" id="humSHT">-<span class="widget-unit">%RH</span></div>
                        </div>
                        <div class="p-3 rounded-3 d-flex align-items-center justify-content-center" style="background: rgba(45, 122, 77, 0.08) !important; color: var(--primary-leaf) !important; border: 1px solid rgba(45, 122, 77, 0.15); width: 50px; height: 50px;">
                            <i class="bi bi-droplet-half fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 3: Suhu SHT31 -->
            <div class="col-xl-3 col-md-6">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Suhu SHT31</span>
                            <div class="widget-val" id="tempSHT">-<span class="widget-unit">°C</span></div>
                        </div>
                        <div class="p-3 rounded-3 d-flex align-items-center justify-content-center" style="background: rgba(239, 108, 0, 0.08) !important; color: var(--neon-orange) !important; border: 1px solid rgba(239, 108, 0, 0.15); width: 50px; height: 50px;">
                            <i class="bi bi-thermometer-sun fs-2"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widget 4: WiFi RSSI -->
            <div class="col-xl-3 col-md-6">
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">WiFi RSSI</span>
                            <div class="widget-val" id="wifiRSSI">-<span class="widget-unit">dBm</span></div>
                        </div>
                        <div class="p-3 rounded-3 d-flex align-items-center justify-content-center" id="wifiRSSIContainer" style="background: rgba(0, 131, 143, 0.08) !important; color: var(--neon-blue) !important; border: 1px solid rgba(0, 131, 143, 0.15); width: 50px; height: 50px;">
                            <i class="bi bi-wifi fs-2" id="wifiRSSIIcon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Baris Kedua: Status & Kontrol Kipas Exhaust -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="glass-card">
                    <!-- Header Section -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom border-light border-opacity-10 pb-3 mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-3 bg-light bg-opacity-5 rounded-3 text-info">
                                <i class="bi bi-fan fs-3" id="exhaustFanLogoMaster"></i>
                            </div>
                            <div>
                                <h5 class="fw-semibold m-0 text-white">Status & Kontrol Kipas Exhaust</h5>
                                <span class="text-muted small">Semua 4 channel relay digunakan untuk pembuangan sirkulasi udara lembap secara independen</span>
                            </div>
                        </div>
                        
                        <!-- Mode Kontrol Toggle -->
                        <div class="form-check form-switch m-0 ps-5 d-flex align-items-center gap-2 bg-light bg-opacity-5 px-4 py-2 rounded-pill border border-light border-opacity-10 shadow-sm">
                            <input class="form-check-input" type="checkbox" role="switch" id="controlModeToggle" style="width: 2.5em; height: 1.25em;">
                            <label class="form-check-label fw-semibold text-white small" for="controlModeToggle" id="controlModeLabel">Mode: AUTO</label>
                        </div>
                    </div>
                    
                    <!-- 4 Channel Grid -->
                    <div class="row g-3">
                        <?php for($i = 1; $i <= 4; $i++): ?>
                        <div class="col-6 col-md-3">
                            <div class="p-3 rounded-3 shadow-sm border border-light border-opacity-10 d-flex flex-column gap-3" style="background: rgba(255,255,255,0.02)">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="fw-bold text-white mb-1">Exhaust <?= $i ?></h6>
                                        <!-- Indikator status real-time alat -->
                                        <div class="d-flex align-items-center gap-1.5 mt-1">
                                            <span class="status-pulse" id="exhaustPulse<?= $i ?>" style="width:8px; height:8px;"></span>
                                            <span class="small fw-semibold text-uppercase" id="exhaustStatusText<?= $i ?>">Checking</span>
                                        </div>
                                    </div>
                                    <div class="p-2 bg-light bg-opacity-5 text-secondary rounded-2" id="exhaustLogoContainer<?= $i ?>">
                                        <i class="bi bi-fan fs-4" id="exhaustLogo<?= $i ?>"></i>
                                    </div>
                                </div>
                                
                                <!-- Slider Kontrol (hanya aktif jika mode MANUAL) -->
                                <div class="form-check form-switch m-0 d-flex align-items-center justify-content-between p-0 border-top border-light border-opacity-10 pt-3">
                                    <span class="small fw-medium text-muted">Kontrol Manual</span>
                                    <input class="form-check-input m-0" type="checkbox" role="switch" id="exhaustControlToggle<?= $i ?>" style="width: 2.2em; height: 1.15em;" disabled>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik Realtime (Chart.js) -->
        <div class="row g-4">
            <!-- Grafik Suhu -->
            <div class="col-lg-6">
                <div class="glass-card">
                    <h5 class="fw-semibold mb-4 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-thermometer-half text-danger"></i> Tren Grafik Suhu (°C)
                    </h5>
                    <div style="position: relative; height: 320px; width: 100%;">
                        <canvas id="temperatureChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Grafik Kelembapan -->
            <div class="col-lg-6">
                <div class="glass-card">
                    <h5 class="fw-semibold mb-4 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-moisture text-primary"></i> Tren Grafik Kelembapan (%RH)
                    </h5>
                    <div style="position: relative; height: 320px; width: 100%;">
                        <canvas id="humidityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Script JavaScript Bootstrap, Jquery, Sweetalert2, and AJAX -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js?v=<?php echo filemtime('js/main.js'); ?>"></script>

    <script>
    let tempChartInstance = null;
    let humChartInstance = null;

    // Inisialisasi Grafik Chart.js
    function initCharts() {
        const ctxTemp = document.getElementById('temperatureChart').getContext('2d');
        const ctxHum = document.getElementById('humidityChart').getContext('2d');

        // Style Chart global styling
        Chart.defaults.color = '#5c6f62';
        Chart.defaults.borderColor = 'rgba(45, 122, 77, 0.06)';

        tempChartInstance = new Chart(ctxTemp, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Suhu DS18B20',
                        data: [],
                        borderColor: '#c62828',
                        backgroundColor: 'rgba(198, 40, 40, 0.04)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Suhu SHT31',
                        data: [],
                        borderColor: '#ef6c00',
                        backgroundColor: 'rgba(239, 108, 0, 0.04)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        title: { display: true, text: 'Suhu (°C)' }
                    }
                }
            }
        });

        humChartInstance = new Chart(ctxHum, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Kelembapan SHT31',
                        data: [],
                        borderColor: '#2d7a4d',
                        backgroundColor: 'rgba(45, 122, 77, 0.08)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Kelembapan (%RH)' }
                    }
                }
            }
        });
    }

    // Memperbarui widget numerik (Setiap 2 Detik)
    function updateTelemetryWidgets() {
        fetch('api/get_latest_sensor.php')
            .then(res => res.json())
            .then(result => {
                if (result.status === 'success') {
                    const data = result.data;

                    // Mengisi widget numerik
                    document.getElementById('tempDS').innerHTML = data.temperature.toFixed(1) + '<span class="widget-unit">°C</span>';
                    document.getElementById('humSHT').innerHTML = data.humidity.toFixed(1) + '<span class="widget-unit">%RH</span>';
                    document.getElementById('tempSHT').innerHTML = data.sht_temperature.toFixed(1) + '<span class="widget-unit">°C</span>';
                    document.getElementById('wifiRSSI').innerHTML = data.wifi + '<span class="widget-unit">dBm</span>';
                    document.getElementById('lastUpdateTime').innerText = formatIndoDate(data.created_at);

                    // Update Wifi Icon
                    const wifiIcon = document.getElementById('wifiRSSIIcon');
                    if (data.wifi > -60) {
                        wifiIcon.className = "bi bi-wifi fs-2";
                    } else if (data.wifi > -80) {
                        wifiIcon.className = "bi bi-wifi-2bar fs-2";
                    } else {
                        wifiIcon.className = "bi bi-wifi-1bar fs-2 text-danger";
                    }

                    // Update Status Relay Exhaust (4 Channels)
                    let anyExhaustOn = false;
                    for (let i = 1; i <= 4; i++) {
                        const statusText = document.getElementById(`exhaustStatusText${i}`);
                        const pulse = document.getElementById(`exhaustPulse${i}`);
                        const exhaustLogo = document.getElementById(`exhaustLogo${i}`);
                        const container = document.getElementById(`exhaustLogoContainer${i}`);
                        const state = data[`exhaust_${i}`] ?? 0;

                        if (state === 1) {
                            anyExhaustOn = true;
                            statusText.innerText = "ON 🟢";
                            statusText.className = "text-success small";
                            pulse.style.backgroundColor = "var(--neon-green)";
                            pulse.style.boxShadow = "0 0 10px var(--neon-green)";
                            pulse.className = "status-pulse active-pulse";
                            exhaustLogo.className = "bi bi-fan fs-4 text-success spin-animation";
                            container.className = "p-2 bg-success bg-opacity-10 rounded-2";
                        } else {
                            statusText.innerText = "OFF 🔴";
                            statusText.className = "text-danger small";
                            pulse.style.backgroundColor = "var(--neon-red)";
                            pulse.style.boxShadow = "0 0 10px var(--neon-red)";
                            pulse.className = "status-pulse";
                            exhaustLogo.className = "bi bi-fan fs-4 text-danger";
                            container.className = "p-2 bg-danger bg-opacity-10 rounded-2";
                        }
                    }

                    // Update Master logo spin animation
                    const masterLogo = document.getElementById('exhaustFanLogoMaster');
                    if (anyExhaustOn) {
                        masterLogo.className = "bi bi-fan fs-3 text-info spin-animation";
                    } else {
                        masterLogo.className = "bi bi-fan fs-3 text-secondary";
                    }

                    // Update Mode Toggle & Sliders dynamically from settings
                    const controlModeToggle = document.getElementById('controlModeToggle');
                    const controlModeLabel = document.getElementById('controlModeLabel');
                    const isManual = (data.mode === 1);

                    // Update mode toggle check state if the user is not currently focusing it
                    if (controlModeToggle && document.activeElement !== controlModeToggle) {
                        controlModeToggle.checked = isManual;
                        controlModeLabel.innerText = isManual ? "Mode: MANUAL" : "Mode: AUTO";
                    }

                    // Update exhaust manual slider toggles
                    for (let i = 1; i <= 4; i++) {
                        const toggle = document.getElementById(`exhaustControlToggle${i}`);
                        if (toggle) {
                            toggle.disabled = !isManual;
                            if (document.activeElement !== toggle) {
                                toggle.checked = (data[`exhaust_${i}_control`] === 1);
                            }
                        }
                    }
                }
            })
            .catch(err => console.error("Error loading telemetry widgets:", err));
    }

    // Memperbarui grafik Chart.js (Setiap 5 Detik)
    function updateCharts() {
        fetch('api/get_chart.php')
            .then(res => res.json())
            .then(result => {
                if (result.status === 'success' && tempChartInstance && humChartInstance) {
                    const labels = result.labels;
                    const dsTemp = result.datasets.temperature_ds;
                    const shtTemp = result.datasets.temperature_sht;
                    const shtHum = result.datasets.humidity;

                    // Update Grafik Suhu
                    tempChartInstance.data.labels = labels;
                    tempChartInstance.data.datasets[0].data = dsTemp;
                    tempChartInstance.data.datasets[1].data = shtTemp;
                    tempChartInstance.update('none'); // Update secara halus

                    // Update Grafik Kelembapan
                    humChartInstance.data.labels = labels;
                    humChartInstance.data.datasets[0].data = shtHum;
                    humChartInstance.update('none');
                }
            })
            .catch(err => console.error("Error loading charts:", err));
    }

    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        updateTelemetryWidgets();
        updateCharts();

        // Control Manual Logic
        const controlModeToggle = document.getElementById('controlModeToggle');
        const controlModeLabel = document.getElementById('controlModeLabel');
        
        // References to 4 sliders
        const exhaustToggles = {};
        for (let i = 1; i <= 4; i++) {
            exhaustToggles[i] = document.getElementById(`exhaustControlToggle${i}`);
        }

        function loadControlModeState() {
            fetch('api/get_settings.php')
                .then(res => res.json())
                .then(result => {
                    if (result.status === 'success') {
                        const settings = result.data;
                        const isManual = (settings.control_mode === 'MANUAL');

                        controlModeToggle.checked = isManual;
                        controlModeLabel.innerText = isManual ? "Mode: MANUAL" : "Mode: AUTO";

                        for (let i = 1; i <= 4; i++) {
                            const isExhaustOn = (settings[`exhaust_${i}_control`] === 'ON');
                            exhaustToggles[i].disabled = !isManual;
                            exhaustToggles[i].checked = isExhaustOn;
                        }
                    }
                })
                .catch(err => console.error("Error loading control mode settings:", err));
        }

        controlModeToggle.addEventListener('change', function() {
            const mode = this.checked ? 'MANUAL' : 'AUTO';
            controlModeLabel.innerText = this.checked ? "Mode: MANUAL" : "Mode: AUTO";
            
            for (let i = 1; i <= 4; i++) {
                exhaustToggles[i].disabled = !this.checked;
            }

            const formData = new FormData();
            formData.append('control_mode', mode);
            
            fetch('api/save_control.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                    alert("Gagal mengubah mode: " + data.message);
                }
            })
            .catch(err => console.error("Error saving control mode:", err));
        });

        // Add event listeners to each of the 4 sliders
        for (let i = 1; i <= 4; i++) {
            exhaustToggles[i].addEventListener('change', function() {
                const state = this.checked ? 'ON' : 'OFF';
                
                const formData = new FormData();
                formData.append('exhaust_id', i);
                formData.append('state', state);
                
                fetch('api/save_control.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'success') {
                        alert(`Gagal mengubah status Kipas Exhaust ${i}: ` + data.message);
                        // Revert checkbox state
                        this.checked = !this.checked;
                    }
                })
                .catch(err => {
                    console.error(`Error saving exhaust ${i} state:`, err);
                    this.checked = !this.checked;
                });
            });
        }

        loadControlModeState();

        // Interval
        setInterval(updateTelemetryWidgets, 2000); // Telemetri widget 2 detik
        setInterval(updateCharts, 5000);           // Grafik Chart 5 detik
    });
    </script>

    <style>
    /* CSS Khusus Animasi Kipas berputar */
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin-animation {
        animation: spin 1.2s linear infinite;
        display: inline-block;
    }
    .active-pulse {
        animation: pulseGlow 1.5s infinite;
    }
    </style>
</body>
</html>
