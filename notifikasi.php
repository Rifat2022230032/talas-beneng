<?php
// notifikasi.php
// Halaman Log Notifikasi Sistem dengan filter dinamis via AJAX
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Notifikasi - Rumah Pengering</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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

        <!-- Judul Halaman & Aksi -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <h3 class="fw-bold text-white m-0 d-flex align-items-center gap-2">
                <i class="bi bi-bell text-info"></i> Log Notifikasi Sistem
            </h3>
            <div class="d-flex gap-2">
                <button onclick="markAllNotificationsAsRead(event)" class="btn btn-outline-info btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3" style="border: 1px solid var(--border-glass);">
                    <i class="bi bi-check-all fs-6"></i> Tandai Semua Dibaca
                </button>
                <button onclick="confirmClearNotifications()" class="btn btn-outline-danger btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3" style="border: 1px solid var(--border-glass);">
                    <i class="bi bi-trash fs-6"></i> Hapus Semua Log
                </button>
            </div>
        </div>

        <!-- Filter Tanggal Dinamis -->
        <div class="glass-card mb-4">
            <h6 class="fw-semibold text-white mb-3"><i class="bi bi-funnel"></i> Filter Log Notifikasi</h6>
            <div class="row g-3">
                <!-- Filter Hari -->
                <div class="col-md-4">
                    <label class="form-label text-muted small">Hari / Tanggal</label>
                    <select class="form-select glass-input" id="filterDay">
                        <option value="">Semua Hari</option>
                        <?php for($i=1; $i<=31; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo sprintf("%02d", $i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Filter Bulan -->
                <div class="col-md-4">
                    <label class="form-label text-muted small">Bulan</label>
                    <select class="form-select glass-input" id="filterMonth">
                        <option value="">Semua Bulan</option>
                        <?php 
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                        foreach($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Tahun -->
                <div class="col-md-4">
                    <label class="form-label text-muted small">Tahun</label>
                    <select class="form-select glass-input" id="filterYear">
                        <option value="">Semua Tahun</option>
                        <option value="2026" selected>2026</option>
                        <option value="2027">2027</option>
                        <option value="2028">2028</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tabel Log Notifikasi -->
        <div class="glass-card">
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">No</th>
                            <th style="width: 200px;">Waktu</th>
                            <th style="width: 150px;">Jenis</th>
                            <th>Pesan Notifikasi</th>
                            <th style="width: 120px;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="notificationTableBody">
                        <tr>
                            <td colspan="5" class="text-center text-muted">Sedang memuat data notifikasi...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Script JavaScript Bootstrap, Jquery, Sweetalert2, and AJAX -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>

    <script>
    // Memuat Notifikasi menggunakan AJAX
    function loadNotifications() {
        const day = document.getElementById('filterDay').value;
        const month = document.getElementById('filterMonth').value;
        const year = document.getElementById('filterYear').value;

        // Bangun query string filter
        let query = `api/get_notifications.php?day=${day}&month=${month}&year=${year}`;

        fetch(query)
            .then(res => res.json())
            .then(result => {
                const tbody = document.getElementById('notificationTableBody');
                tbody.innerHTML = ''; // Reset tabel

                if (result.status === 'success') {
                    const data = result.data;
                    
                    if (data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada log notifikasi yang cocok dengan filter.</td></tr>`;
                        return;
                    }

                    data.forEach((item, index) => {
                        let badgeClass = 'bg-secondary';
                        let typeText = item.type;
                        let icon = '<i class="bi bi-info-circle text-info fs-5"></i>';

                        // Konfigurasi Badge Tipe Notifikasi & Ikon
                        switch (item.type.toUpperCase()) {
                            case 'SUCCESS':
                                badgeClass = 'bg-success text-dark';
                                icon = '<i class="bi bi-check-circle-fill text-success fs-5"></i>';
                                break;
                            case 'WARNING':
                                badgeClass = 'bg-warning text-dark';
                                icon = '<i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>';
                                break;
                            case 'ERROR':
                                badgeClass = 'bg-danger';
                                icon = '<i class="bi bi-x-circle-fill text-danger fs-5"></i>';
                                break;
                            case 'INFO':
                                badgeClass = 'bg-info text-dark';
                                icon = '<i class="bi bi-info-circle-fill text-info fs-5"></i>';
                                break;
                        }

                        const statusBadge = item.status === 'unread' 
                            ? `<button onclick="markSingleAsRead(${item.id})" class="btn p-0 border-0" title="Klik untuk tandai dibaca"><span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50">Baru (Klik Dibaca)</span></button>` 
                            : `<span class="badge bg-secondary bg-opacity-10 text-muted border border-secondary border-opacity-25">Dibaca</span>`;

                        tbody.innerHTML += `
                            <tr style="${item.status === 'unread' ? 'background: rgba(2, 132, 199, 0.02) !important;' : ''}">
                                <td>${index + 1}</td>
                                <td class="text-white">${formatIndoDate(item.created_at)}</td>
                                <td><span class="badge ${badgeClass} fw-bold rounded-pill text-uppercase px-3 py-1.5">${typeText}</span></td>
                                <td class="text-white">
                                    <div class="d-flex align-items-center gap-2">
                                        ${icon}
                                        <span>${item.message}</span>
                                    </div>
                                </td>
                                <td>${statusBadge}</td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Gagal mengambil notifikasi: ${result.message}</td></tr>`;
                }
            })
            .catch(err => {
                console.error("Error loading notifications:", err);
                const tbody = document.getElementById('notificationTableBody');
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Koneksi server terputus. Gagal memuat data.</td></tr>`;
            });
    }

    // Tandai satu notifikasi telah dibaca
    function markSingleAsRead(id) {
        fetch('api/mark_single_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        })
        .then(res => res.json())
        .then(result => {
            if (result.status === 'success') {
                loadNotifications();
                if (typeof checkRealtimeNotifications === 'function') {
                    checkRealtimeNotifications();
                }
            }
        })
        .catch(err => console.error("Gagal menandai dibaca:", err));
    }

    // Hapus semua log notifikasi dengan konfirmasi SweetAlert
    function confirmClearNotifications() {
        Swal.fire({
            title: 'Hapus Semua Log Notifikasi?',
            text: "Tindakan ini akan menghapus permanen seluruh riwayat notifikasi!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api/clear_notifications.php', { method: 'POST' })
                    .then(res => res.json())
                    .then(resData => {
                        if (resData.status === 'success') {
                            Swal.fire(
                                'Terhapus!',
                                'Semua log notifikasi telah berhasil dibersihkan.',
                                'success'
                            );
                            loadNotifications();
                            if (typeof checkRealtimeNotifications === 'function') {
                                checkRealtimeNotifications();
                            }
                        } else {
                            Swal.fire('Gagal!', resData.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Gagal!', 'Koneksi database bermasalah.', 'error');
                    });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadNotifications();

        // Event listener saat filter diubah
        document.getElementById('filterDay').addEventListener('change', loadNotifications);
        document.getElementById('filterMonth').addEventListener('change', loadNotifications);
        document.getElementById('filterYear').addEventListener('change', loadNotifications);

        // Auto refresh notifikasi setiap 5 detik
        setInterval(loadNotifications, 5000);
    });
    </script>
</body>
</html>
