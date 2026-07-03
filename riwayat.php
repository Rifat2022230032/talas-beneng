<?php
// riwayat.php
// Halaman Riwayat Data Sensor menggunakan DataTables dan Fitur Ekspor (Excel, PDF)
require_once 'config.php';

// Ambil parameter filter dari URL GET
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_year = isset($_GET['year']) ? $_GET['year'] : '2026'; // Default tahun berjalan

try {
    $where_clauses = [];
    $params = [];

    // Filter tanggal spesifik
    if (!empty($filter_date)) {
        $where_clauses[] = "DATE(created_at) = ?";
        $params[] = $filter_date;
    }

    // Filter bulan
    if (!empty($filter_month)) {
        $where_clauses[] = "MONTH(created_at) = ?";
        $params[] = (int)$filter_month;
    }

    // Filter tahun
    if (!empty($filter_year)) {
        $where_clauses[] = "YEAR(created_at) = ?";
        $params[] = (int)$filter_year;
    }

    $where_sql = "";
    if (count($where_clauses) > 0) {
        $where_sql = "WHERE " . implode(" AND ", $where_clauses);
    }

    // Query data sensor historis
    $query = "SELECT * FROM sensor_log $where_sql ORDER BY id DESC LIMIT 5000";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Gagal memuat data riwayat: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Data - Rumah Pengering</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
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

        <!-- Judul Halaman & Aksi Hapus -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <h3 class="fw-bold text-white m-0 d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-info"></i> Riwayat Data Sensor
            </h3>
            <div class="d-flex gap-2">
                <button onclick="deleteSelectedSensors()" id="btnDeleteSelectedSensors" class="btn btn-outline-danger btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3 d-none" style="border: 1px solid var(--border-glass);">
                    <i class="bi bi-trash3-fill"></i> Hapus Terpilih (<span id="selectedSensorsCount">0</span>)
                </button>
                <button onclick="deleteAllSensors()" id="btnDeleteAllSensors" class="btn btn-outline-danger btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3" style="border: 1px solid var(--border-glass);">
                    <i class="bi bi-trash3"></i> Hapus Semua
                </button>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="glass-card mb-4">
            <h6 class="fw-semibold text-white mb-3"><i class="bi bi-funnel"></i> Filter Riwayat</h6>
            <form method="GET" action="riwayat.php" class="row g-3 align-items-end">
                <!-- Filter Tanggal -->
                <div class="col-md-3">
                    <label class="form-label text-muted small">Pilih Tanggal</label>
                    <input type="date" class="form-control glass-input" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>

                <!-- Filter Bulan -->
                <div class="col-md-3">
                    <label class="form-label text-muted small">Bulan</label>
                    <select class="form-select glass-input" name="month">
                        <option value="">Semua Bulan</option>
                        <?php 
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                        foreach($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo ($filter_month == $num) ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Tahun -->
                <div class="col-md-3">
                    <label class="form-label text-muted small">Tahun</label>
                    <select class="form-select glass-input" name="year">
                        <option value="">Semua Tahun</option>
                        <option value="2026" <?php echo ($filter_year == '2026') ? 'selected' : ''; ?>>2026</option>
                        <option value="2027" <?php echo ($filter_year == '2027') ? 'selected' : ''; ?>>2027</option>
                        <option value="2028" <?php echo ($filter_year == '2028') ? 'selected' : ''; ?>>2028</option>
                    </select>
                </div>

                <!-- Tombol Submit -->
                <div class="col-md-3">
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-info w-100 py-2.5 fw-semibold"><i class="bi bi-search"></i> Cari Data</button>
                        <a href="riwayat.php" class="btn btn-outline-secondary py-2.5 text-white" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabel Riwayat DataTables -->
        <div class="glass-card">
            <div class="table-responsive">
                <table class="table custom-table table-hover" id="historyTable">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllSensors" class="form-check-input" style="width: 1.15em; height: 1.15em;"></th>
                            <th>No</th>
                            <th>Waktu Pengambilan</th>
                            <th>Suhu DS18B20 (°C)</th>
                            <th>Suhu SHT31 (°C)</th>
                            <th>Kelembapan SHT31 (%RH)</th>
                            <th>Status Kipas Exhaust</th>
                            <th>Kekuatan Sinyal WiFi (RSSI)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $index => $row): ?>
                                <tr>
                                    <td class="text-center"><input type="checkbox" class="form-check-input sensor-checkbox" value="<?php echo $row['id']; ?>" style="width: 1.15em; height: 1.15em;"></td>
                                    <td><?php echo $index + 1; ?></td>
                                    <td class="text-white"><?php echo $row['created_at']; ?></td>
                                    <td class="text-white fw-bold"><?php echo number_format($row['temperature'], 1); ?> °C</td>
                                    <td class="text-white"><?php echo number_format($row['sht_temperature'], 1); ?> °C</td>
                                    <td class="text-info fw-bold"><?php echo number_format($row['humidity'], 1); ?> %RH</td>
                                    <td>
                                        <?php if ($row['exhaust'] == 1): ?>
                                            <span class="badge bg-success text-dark px-3 py-1.5 fw-bold rounded-pill">AKTIF (ON)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger px-3 py-1.5 fw-bold rounded-pill">MATI (OFF)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['wifi']; ?> dBm</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Belum ada riwayat data untuk filter yang dipilih.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- JQuery & Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>

    <!-- DataTables & Buttons Extension JS -->
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <script>
    $(document).ready(function() {
        // Inisialisasi DataTables
        const table = $('#historyTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/id.json' // Bahasa Indonesia
            },
            order: [[1, 'asc']], // Urutkan nomor No (index 1) dari kecil ke besar
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
            dom: "<'row mb-3 align-items-center'<'col-sm-6'l><'col-sm-6 text-end'B>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row mt-3 align-items-center'<'col-sm-5'i><'col-sm-7'p>>",
            buttons: [
                {
                    extend: 'excelHtml5',
                    className: 'btn btn-success btn-sm px-3 border-0 me-2 rounded-2',
                    text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                    title: 'Laporan Riwayat Data Sensor Rumah Pengering',
                    exportOptions: {
                        columns: [1, 2, 3, 4, 5, 6, 7]
                    }
                },
                {
                    extend: 'pdfHtml5',
                    className: 'btn btn-danger btn-sm px-3 border-0 rounded-2',
                    text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                    title: 'Laporan Riwayat Data Sensor Rumah Pengering',
                    exportOptions: {
                        columns: [1, 2, 3, 4, 5, 6, 7]
                    },
                    customize: function (doc) {
                        // Kustomisasi layout pdf
                        doc.content[1].table.widths = ['5%', '25%', '15%', '15%', '15%', '15%', '10%'];
                        doc.styles.tableHeader.fillColor = '#101628';
                        doc.styles.tableHeader.color = '#ffffff';
                        doc.styles.tableHeader.alignment = 'center';
                    }
                }
            ],
            // Pengaturan urutan dan pencarian kolom
            columnDefs: [
                {
                    targets: 0,
                    orderable: false,
                    searchable: false,
                },
                {
                    targets: 2,
                    render: function(data, type, row) {
                        if (type === 'display') {
                            return formatIndoDate(data);
                        }
                        return data;
                    }
                }
            ]
        });

        // Event handler "Select All" checkbox
        $('#selectAllSensors').on('click', function() {
            // Dapatkan baris yang cocok dengan pencarian / filter DataTables saat ini
            const rows = table.rows({ 'search': 'applied' }).nodes();
            $('input[type="checkbox"]', rows).prop('checked', this.checked);
            updateSensorsSelectionUI();
        });

        // Event handler checkbox baris individual (Delegated event untuk kecocokan paging/searching)
        $('#historyTable tbody').on('change', 'input[type="checkbox"].sensor-checkbox', function() {
            const elSelectAll = $('#selectAllSensors').get(0);
            if (!this.checked && elSelectAll && elSelectAll.checked && ('indeterminate' in elSelectAll)) {
                elSelectAll.indeterminate = true;
            }
            updateSensorsSelectionUI();
        });

        // Fungsi memperbarui UI tombol Hapus Terpilih
        function updateSensorsSelectionUI() {
            // Hitung semua checkbox yang dicentang di seluruh baris DataTables
            const rows = table.rows().nodes();
            const checkedCount = $('.sensor-checkbox:checked', rows).length;
            const btnDeleteSelected = $('#btnDeleteSelectedSensors');
            const selectedCountSpan = $('#selectedSensorsCount');

            if (checkedCount > 0) {
                btnDeleteSelected.removeClass('d-none');
                selectedCountSpan.text(checkedCount);
            } else {
                btnDeleteSelected.addClass('d-none');
                selectedCountSpan.text('0');
            }
        }

        // Ekspos fungsi hapus secara global agar bisa dipanggil dari inline onclick
        window.deleteSelectedSensors = function() {
            const rows = table.rows().nodes();
            const checkedBoxes = $('.sensor-checkbox:checked', rows);
            const ids = Array.from(checkedBoxes).map(chk => parseInt($(chk).val()));
            
            if (ids.length === 0) return;

            Swal.fire({
                title: `Hapus ${ids.length} Baris Data Sensor?`,
                text: "Data riwayat sensor yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    ids.forEach(id => formData.append('ids[]', id));

                    fetch('api/delete_sensors.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus!',
                                text: data.message
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Gagal!', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Gagal!', 'Koneksi database bermasalah.', 'error');
                    });
                }
            });
        };

        window.deleteAllSensors = function() {
            Swal.fire({
                title: 'Hapus Semua Data Sensor?',
                text: "Seluruh riwayat log data sensor akan dikosongkan secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus Semua!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'all');

                    fetch('api/delete_sensors.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Dibersihkan!',
                                text: data.message
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Gagal!', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Gagal!', 'Koneksi database bermasalah.', 'error');
                    });
                }
            });
        };
    });
    </script>
</body>
</html>
