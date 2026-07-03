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
    <link rel="stylesheet" href="css/style.css">
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
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3">
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
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3">
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
                        <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3">
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
                        <div class="p-3 bg-info bg-opacity-10 text-info rounded-3" id="wifiRSSIContainer">
                            <i class="bi bi-wifi fs-2" id="wifiRSSIIcon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Baris Kedua: Status Exhaust Relay -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="glass-card d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-light bg-opacity-5 rounded-3">
                            <i class="bi bi-fan fs-3" id="exhaustFanLogo"></i>
                        </div>
                        <div>
                            <h5 class="fw-semibold m-0 text-white">Status Relay Kipas Exhaust</h5>
                            <span class="text-muted small">Semua 4 channel relay digunakan untuk pembuangan sirkulasi udara lembap</span>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <!-- Indikator 🟢 / 🔴 -->
                        <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-pill shadow-inner" id="exhaustStatusContainer" style="background: rgba(0,0,0,0.03); border: 1px solid var(--border-glass);">
                            <span class="status-pulse" id="exhaustPulse" style="width:12px; height:12px;"></span>
                            <strong class="text-uppercase small" id="exhaustStatusText">Checking</strong>
                        </div>

                        <!-- Mode Kontrol Toggle -->
                        <div class="form-check form-switch m-0 ps-5 d-flex align-items-center gap-2 border-start border-secondary border-opacity-25 ms-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="controlModeToggle" style="width: 2.5em; height: 1.25em;">
                            <label class="form-check-label fw-semibold text-white small" for="controlModeToggle" id="controlModeLabel">Mode: AUTO</label>
                        </div>

                        <!-- Exhaust Toggle (hanya aktif jika mode MANUAL) -->
                        <div class="form-check form-switch m-0 ps-5 d-flex align-items-center gap-2 border-start border-secondary border-opacity-25 ms-2" id="manualExhaustWrapper">
                            <input class="form-check-input" type="checkbox" role="switch" id="exhaustControlToggle" style="width: 2.5em; height: 1.25em;" disabled>
                            <label class="form-check-label fw-semibold text-white small" for="exhaustControlToggle" id="exhaustControlLabel">Exhaust: OFF</label>
                        </div>
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
    <script src="js/main.js"></script>

    <script>
    let tempChartInstance = null;
    let humChartInstance = null;

    // Inisialisasi Grafik Chart.js
    function initCharts() {
        const ctxTemp = document.getElementById('temperatureChart').getContext('2d');
        const ctxHum = document.getElementById('humidityChart').getContext('2d');

        // Style Chart global styling
        Chart.defaults.color = '#8c9bb4';
        Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.05)';

        tempChartInstance = new Chart(ctxTemp, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Suhu DS18B20',
                        data: [],
                        borderColor: '#ff3c6a',
                        backgroundColor: 'rgba(255, 60, 106, 0.05)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Suhu SHT31',
                        data: [],
                        borderColor: '#ff9900',
                        backgroundColor: 'rgba(255, 153, 0, 0.05)',
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
                        borderColor: '#00d2ff',
                        backgroundColor: 'rgba(0, 210, 255, 0.1)',
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

                    // Update Status Relay Exhaust
                    const statusText = document.getElementById('exhaustStatusText');
                    const pulse = document.getElementById('exhaustPulse');
                    const exhaustLogo = document.getElementById('exhaustFanLogo');
                    
                    if (data.exhaust === 1) {
                        statusText.innerText = "EXHAUST ON 🟢";
                        statusText.className = "text-success small";
                        pulse.style.backgroundColor = "var(--neon-green)";
                        pulse.style.boxShadow = "0 0 10px var(--neon-green)";
                        pulse.className = "status-pulse active-pulse";
                        exhaustLogo.className = "bi bi-fan fs-3 text-success spin-animation";
                    } else {
                        statusText.innerText = "EXHAUST OFF 🔴";
                        statusText.className = "text-danger small";
                        pulse.style.backgroundColor = "var(--neon-red)";
                        pulse.style.boxShadow = "0 0 10px var(--neon-red)";
                        pulse.className = "status-pulse";
                        exhaustLogo.className = "bi bi-fan fs-3 text-secondary";
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
        const exhaustControlToggle = document.getElementById('exhaustControlToggle');
        const exhaustControlLabel = document.getElementById('exhaustControlLabel');

        function loadControlModeState() {
            fetch('api/get_settings.php')
                .then(res => res.json())
                .then(result => {
                    if (result.status === 'success') {
                        const settings = result.data;
                        const isManual = (settings.control_mode === 'MANUAL');
                        const isExhaustOn = (settings.exhaust_control === 'ON');

                        controlModeToggle.checked = isManual;
                        controlModeLabel.innerText = isManual ? "Mode: MANUAL" : "Mode: AUTO";

                        exhaustControlToggle.disabled = !isManual;
                        exhaustControlToggle.checked = isExhaustOn;
                        exhaustControlLabel.innerText = isExhaustOn ? "Exhaust: ON" : "Exhaust: OFF";
                    }
                })
                .catch(err => console.error("Error loading control mode settings:", err));
        }

        controlModeToggle.addEventListener('change', function() {
            const mode = this.checked ? 'MANUAL' : 'AUTO';
            controlModeLabel.innerText = this.checked ? "Mode: MANUAL" : "Mode: AUTO";
            
            exhaustControlToggle.disabled = !this.checked;

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

        exhaustControlToggle.addEventListener('change', function() {
            const state = this.checked ? 'ON' : 'OFF';
            exhaustControlLabel.innerText = this.checked ? "Exhaust: ON" : "Exhaust: OFF";

            const formData = new FormData();
            formData.append('exhaust_control', state);
            
            fetch('api/save_control.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                    alert("Gagal mengubah status kipas: " + data.message);
                }
            })
            .catch(err => console.error("Error saving exhaust state:", err));
        });

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
