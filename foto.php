<?php
// foto.php
// Halaman Khusus Galeri Foto dari ESP32-CAM dengan Realtime Auto-update
require_once 'config.php';

// Ambil status kamera saat ini dari settings
$settings = getSystemSettings();
$camera_status = isset($settings['camera_status']) ? $settings['camera_status'] : 'ON';

// Ambil foto histori awal secara server-side
try {
    $stmt = $pdo->query("SELECT * FROM camera_log ORDER BY id DESC LIMIT 24");
    $initial_photos = $stmt->fetchAll();
} catch (PDOException $e) {
    $initial_photos = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Foto ESP32-CAM - Rumah Pengering</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
            <i class="bi bi-camera text-info"></i> Galeri Foto Monitoring
        </h3>

        <!-- FOTO TERBARU (HERO VIEW) -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="glass-card p-4">
                    <h5 class="fw-semibold text-white mb-3"><i class="bi bi-image"></i> Foto Pengeringan Terkini</h5>
                    <div class="row align-items-center g-4">
                        <div class="col-lg-6 text-center">
                            <div class="position-relative overflow-hidden rounded-3 border border-secondary border-opacity-25" style="max-height: 400px; background: rgba(0,0,0,0.02);">
                                <img src="<?php echo !empty($initial_photos) ? $initial_photos[0]['filepath'] : 'https://placehold.co/600x400?text=Belum+Ada+Foto'; ?>" 
                                     alt="Foto Terbaru" 
                                     id="latestHeroImage" 
                                     class="img-fluid rounded shadow-lg" 
                                     style="object-fit: contain; cursor: pointer; max-height: 400px;"
                                     onclick="openLightbox(this.src, document.getElementById('latestHeroTime').innerText)">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="p-3">
                                <span class="badge bg-success text-dark mb-2 px-3 py-1.5 fw-semibold rounded-pill">Status Live</span>
                                <h2 class="text-white fw-bold mb-3">Kondisi Daun Talas Beneng</h2>
                                <p class="text-muted leading-relaxed">Gambar di samping diambil secara otomatis oleh kamera ESP32-CAM yang ditempatkan di dalam ruang pengering. Foto diperbarui secara otomatis setiap 5 detik tanpa perlu me-reload halaman.</p>
                                
                                <div class="mt-4 pt-3 border-top border-light border-opacity-10">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="p-2.5 bg-info bg-opacity-10 text-info rounded-circle">
                                            <i class="bi bi-clock-fill fs-4"></i>
                                        </div>
                                        <div>
                                            <div class="text-muted small">Waktu Pengambilan</div>
                                            <div class="fw-bold text-white fs-5" id="latestHeroTime">
                                                <?php echo !empty($initial_photos) ? $initial_photos[0]['created_at'] : '-'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kontrol Kamera Stop/Start -->
                                <div class="mt-4 pt-3 border-top border-light border-opacity-10">
                                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="p-2.5 rounded-circle" id="cameraStatusIcon" style="background: <?php echo ($camera_status === 'ON') ? 'rgba(25, 135, 84, 0.15)' : 'rgba(220, 53, 69, 0.15)'; ?>;">
                                                <i class="bi <?php echo ($camera_status === 'ON') ? 'bi-camera-video-fill text-success' : 'bi-camera-video-off-fill text-danger'; ?> fs-4" id="cameraStatusIconInner"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Status Kamera</div>
                                                <div class="fw-bold fs-5" id="cameraStatusLabel" style="color: <?php echo ($camera_status === 'ON') ? '#198754' : '#dc3545'; ?>;">
                                                    <?php echo ($camera_status === 'ON') ? 'Aktif — Mengambil Foto' : 'Berhenti — Tidak Mengambil Foto'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <button onclick="toggleCameraStatus()" 
                                                id="btnToggleCamera" 
                                                class="btn <?php echo ($camera_status === 'ON') ? 'btn-outline-danger' : 'btn-outline-success'; ?> fw-semibold d-flex align-items-center gap-2 px-4 py-2 rounded-pill"
                                                style="border-width: 2px; transition: all 0.3s ease;">
                                            <i class="bi <?php echo ($camera_status === 'ON') ? 'bi-stop-circle-fill' : 'bi-play-circle-fill'; ?> fs-5"></i>
                                            <span id="btnToggleCameraText"><?php echo ($camera_status === 'ON') ? 'Stop Kamera' : 'Aktifkan Kamera'; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header Galeri & Aksi -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <h4 class="fw-semibold text-white m-0"><i class="bi bi-images text-info"></i> Semua Foto Galeri</h4>
            <div class="d-flex gap-2">
                <button onclick="toggleSelectMode()" id="btnSelectMode" class="btn btn-outline-info btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3" style="border: 1px solid var(--border-glass);">
                    <i class="bi bi-check2-square"></i> Mode Pilih
                </button>
                <button onclick="deleteSelectedPhotos()" id="btnDeleteSelected" class="btn btn-outline-danger btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3 d-none" style="border: 1px solid var(--border-glass);">
                    <i class="bi bi-trash3-fill"></i> Hapus Terpilih (<span id="selectedCount">0</span>)
                </button>
                <button onclick="deleteAllPhotos()" id="btnDeleteAll" class="btn btn-outline-danger btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3" style="border: 1px solid var(--border-glass);">
                    <i class="bi bi-trash"></i> Hapus Semua
                </button>
            </div>
        </div>

        <!-- Filter Galeri Tanggal -->
        <div class="glass-card mb-4">
            <div class="row align-items-center g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted small"><i class="bi bi-calendar3"></i> Filter Berdasarkan Tanggal</label>
                    <div class="input-group">
                        <input type="date" class="form-control glass-input" id="galleryFilterDate" onchange="filterGallery()">
                        <button onclick="clearGalleryFilter()" class="btn btn-outline-secondary btn-sm px-3" type="button" id="btnClearFilter" style="border: 1px solid var(--border-glass);"><i class="bi bi-x-circle"></i> Bersihkan</button>
                    </div>
                </div>
                <div class="col-md-6 text-md-end pt-md-4">
                    <span class="text-muted small">Status Tampilan: <strong id="galleryStatusText" class="text-white">Semua Foto (Limit 100)</strong></span>
                </div>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4" id="galleryContainer">
            <?php if (!empty($initial_photos)): ?>
                <?php foreach ($initial_photos as $photo): ?>
                    <div class="col gallery-card-wrapper" data-id="<?php echo $photo['id']; ?>">
                        <div class="glass-card p-2 gallery-item position-relative" style="cursor: pointer;">
                            <!-- Checkbox Pilihan -->
                            <div class="position-absolute top-0 end-0 m-2 select-checkbox-container d-none" style="z-index: 10;">
                                <input type="checkbox" class="form-check-input photo-checkbox" value="<?php echo $photo['id']; ?>" style="width: 1.35em; height: 1.35em; cursor: pointer;" onclick="event.stopPropagation(); handleCheckboxChange();">
                            </div>
                            
                            <div class="photo-click-area" onclick="handlePhotoClick(this, event, '<?php echo $photo['filepath']; ?>', '<?php echo $photo['created_at']; ?>')">
                                <img src="<?php echo $photo['filepath']; ?>" alt="<?php echo $photo['filename']; ?>" class="rounded">
                                <div class="gallery-overlay">
                                    <span class="fw-medium d-block"><i class="bi bi-calendar"></i> <?php echo basename($photo['filename'], '.jpg'); ?></span>
                                    <span class="text-muted small"><i class="bi bi-clock"></i> <?php echo date('H:i:s', strtotime($photo['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12" id="emptyGalleryMessage">
                    <div class="glass-card text-center text-muted py-5">
                        <i class="bi bi-camera-video-off fs-1 mb-3"></i>
                        <p class="m-0">Belum ada foto yang diunggah ke server.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Lightbox Modal untuk memperbesar gambar -->
    <div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-glass-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="lightboxTitle">Foto Pengeringan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img src="" id="lightboxImage" class="img-fluid w-100 rounded-bottom" style="max-height: 80vh; object-fit: contain;">
                </div>
                <div class="modal-footer justify-content-between">
                    <span class="text-muted small" id="lightboxTimeText">-</span>
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script JavaScript Bootstrap, Jquery, Sweetalert2, and AJAX -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js?v=<?php echo filemtime('js/main.js'); ?>"></script>

    <script>
    let latestPhotoId = <?php echo !empty($initial_photos) ? $initial_photos[0]['id'] : 0; ?>;
    let lightboxModalInstance = null;
    let isSelectMode = false;
    let currentCameraStatus = '<?php echo $camera_status; ?>';

    // ==============================
    // Fungsi Toggle Status Kamera (Stop/Start)
    // ==============================
    function toggleCameraStatus() {
        const newStatus = (currentCameraStatus === 'ON') ? 'OFF' : 'ON';
        const actionTitle = (newStatus === 'OFF') ? 'Stop Kamera?' : 'Aktifkan Kamera?';
        const actionText = (newStatus === 'OFF') 
            ? 'ESP32-CAM akan berhenti mengambil foto hingga diaktifkan kembali.' 
            : 'ESP32-CAM akan mulai mengambil foto kembali secara otomatis.';
        const actionIcon = (newStatus === 'OFF') ? 'warning' : 'question';
        const confirmBtnColor = (newStatus === 'OFF') ? '#dc3545' : '#198754';
        const confirmBtnText = (newStatus === 'OFF') ? 'Ya, Hentikan!' : 'Ya, Aktifkan!';

        Swal.fire({
            title: actionTitle,
            text: actionText,
            icon: actionIcon,
            showCancelButton: true,
            confirmButtonColor: confirmBtnColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmBtnText,
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('camera_status', newStatus);

                // Tampilkan loading
                const btn = document.getElementById('btnToggleCamera');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Memproses...';

                fetch('api/camera_control.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        currentCameraStatus = data.camera_status;
                        updateCameraUI(data.camera_status);
                        
                        const successMsg = (data.camera_status === 'OFF') 
                            ? 'Kamera berhasil dihentikan. ESP32-CAM akan berhenti mengambil foto.' 
                            : 'Kamera berhasil diaktifkan. ESP32-CAM akan mulai mengambil foto.';
                        Swal.fire({
                            title: (data.camera_status === 'OFF') ? 'Kamera Dihentikan!' : 'Kamera Aktif!',
                            text: successMsg,
                            icon: 'success',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Gagal!', data.message || 'Terjadi kesalahan.', 'error');
                        btn.disabled = false;
                        updateCameraUI(currentCameraStatus); // Kembalikan UI
                    }
                })
                .catch(err => {
                    console.error('Error toggling camera:', err);
                    Swal.fire('Gagal!', 'Koneksi ke server terputus.', 'error');
                    btn.disabled = false;
                    updateCameraUI(currentCameraStatus);
                });
            }
        });
    }

    // Fungsi untuk memperbarui tampilan UI tombol dan status kamera
    function updateCameraUI(status) {
        const btn = document.getElementById('btnToggleCamera');
        const btnText = document.getElementById('btnToggleCameraText');
        const statusIcon = document.getElementById('cameraStatusIcon');
        const statusIconInner = document.getElementById('cameraStatusIconInner');
        const statusLabel = document.getElementById('cameraStatusLabel');

        btn.disabled = false;

        if (status === 'ON') {
            // UI Aktif
            btn.className = 'btn btn-outline-danger fw-semibold d-flex align-items-center gap-2 px-4 py-2 rounded-pill';
            btn.style.borderWidth = '2px';
            btn.innerHTML = '<i class="bi bi-stop-circle-fill fs-5"></i> <span id="btnToggleCameraText">Stop Kamera</span>';
            statusIcon.style.background = 'rgba(25, 135, 84, 0.15)';
            statusIconInner.className = 'bi bi-camera-video-fill text-success fs-4';
            statusLabel.style.color = '#198754';
            statusLabel.innerText = 'Aktif — Mengambil Foto';
        } else {
            // UI Berhenti
            btn.className = 'btn btn-outline-success fw-semibold d-flex align-items-center gap-2 px-4 py-2 rounded-pill';
            btn.style.borderWidth = '2px';
            btn.innerHTML = '<i class="bi bi-play-circle-fill fs-5"></i> <span id="btnToggleCameraText">Aktifkan Kamera</span>';
            statusIcon.style.background = 'rgba(220, 53, 69, 0.15)';
            statusIconInner.className = 'bi bi-camera-video-off-fill text-danger fs-4';
            statusLabel.style.color = '#dc3545';
            statusLabel.innerText = 'Berhenti — Tidak Mengambil Foto';
        }
    }

    // Fungsi untuk memperbesar gambar dalam Lightbox/Modal
    function openLightbox(src, time) {
        document.getElementById('lightboxImage').src = src;
        document.getElementById('lightboxTimeText').innerText = 'Diambil pada: ' + formatIndoDate(time);
        
        if (!lightboxModalInstance) {
            lightboxModalInstance = new bootstrap.Modal(document.getElementById('lightboxModal'));
        }
        lightboxModalInstance.show();
    }

    // Fungsi untuk menangani click pada item foto di galeri
    function handlePhotoClick(el, event, filepath, createdAt) {
        if (isSelectMode) {
            event.preventDefault();
            // Cari checkbox pada card tersebut dan toggle status centangnya
            const card = el.closest('.gallery-item');
            const checkbox = card.querySelector('.photo-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                handleCheckboxChange();
            }
        } else {
            openLightbox(filepath, createdAt);
        }
    }

    // Fungsi untuk mengaktifkan/menonaktifkan mode pilih foto
    function toggleSelectMode() {
        isSelectMode = !isSelectMode;
        const selectContainers = document.querySelectorAll('.select-checkbox-container');
        const btnSelectMode = document.getElementById('btnSelectMode');
        const btnDeleteSelected = document.getElementById('btnDeleteSelected');
        
        if (isSelectMode) {
            // Tampilkan seluruh checkbox
            selectContainers.forEach(el => el.classList.remove('d-none'));
            btnSelectMode.innerHTML = '<i class="bi bi-x-circle"></i> Batal Pilih';
            btnSelectMode.className = 'btn btn-info btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3';
        } else {
            // Sembunyikan checkbox dan matikan pilihan
            selectContainers.forEach(el => el.classList.add('d-none'));
            document.querySelectorAll('.photo-checkbox').forEach(chk => chk.checked = false);
            btnSelectMode.innerHTML = '<i class="bi bi-check2-square"></i> Mode Pilih';
            btnSelectMode.className = 'btn btn-outline-info btn-sm fw-semibold d-flex align-items-center gap-2 py-2 px-3 rounded-3';
            btnDeleteSelected.classList.add('d-none');
            document.getElementById('selectedCount').innerText = '0';
        }
    }

    // Fungsi saat status checkbox berubah
    function handleCheckboxChange() {
        const checkboxes = document.querySelectorAll('.photo-checkbox:checked');
        const btnDeleteSelected = document.getElementById('btnDeleteSelected');
        const selectedCount = document.getElementById('selectedCount');
        
        if (checkboxes.length > 0) {
            btnDeleteSelected.classList.remove('d-none');
            selectedCount.innerText = checkboxes.length;
        } else {
            btnDeleteSelected.classList.add('d-none');
            selectedCount.innerText = '0';
        }
    }

    // Hapus foto-foto yang dipilih saja
    function deleteSelectedPhotos() {
        const checkedBoxes = document.querySelectorAll('.photo-checkbox:checked');
        const ids = Array.from(checkedBoxes).map(chk => parseInt(chk.value));
        
        if (ids.length === 0) return;

        Swal.fire({
            title: `Hapus ${ids.length} Foto Terpilih?`,
            text: "Foto yang dihapus tidak dapat dipulihkan kembali!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Siapkan form data
                const formData = new FormData();
                ids.forEach(id => formData.append('ids[]', id));

                fetch('api/delete_photos.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Terhapus!', data.message, 'success');
                        
                        // Hapus card dari DOM secara interaktif
                        ids.forEach(id => {
                            const cardWrapper = document.querySelector(`.gallery-card-wrapper[data-id="${id}"]`);
                            if (cardWrapper) {
                                cardWrapper.remove();
                            }
                        });

                        // Sembunyikan tombol hapus dan set hitungan kembali ke 0
                        document.getElementById('btnDeleteSelected').classList.add('d-none');
                        document.getElementById('selectedCount').innerText = '0';

                        // Cek apakah galeri sudah kosong
                        const remaining = document.querySelectorAll('.gallery-card-wrapper');
                        if (remaining.length === 0) {
                            showEmptyGalleryMessage();
                        }
                    } else {
                        Swal.fire('Gagal!', data.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Gagal!', 'Terjadi gangguan koneksi server.', 'error');
                });
            }
        });
    }

    // Hapus semua foto di galeri
    function deleteAllPhotos() {
        Swal.fire({
            title: 'Hapus Semua Foto?',
            text: "Seluruh foto hasil monitoring di galeri akan terhapus secara permanen!",
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

                fetch('api/delete_photos.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Terhapus!', data.message, 'success');
                        
                        // Kosongkan kontainer galeri
                        document.getElementById('galleryContainer').innerHTML = '';
                        showEmptyGalleryMessage();
                        
                        // Reset Tampilan Hero ke default placeholder
                        document.getElementById('latestHeroImage').src = 'https://placehold.co/600x400?text=Belum+Ada+Foto';
                        document.getElementById('latestHeroTime').innerText = '-';
                        latestPhotoId = 0;
                    } else {
                        Swal.fire('Gagal!', data.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Gagal!', 'Terjadi gangguan koneksi server.', 'error');
                });
            }
        });
    }

    // Menampilkan pesan galeri kosong secara dinamis
    function showEmptyGalleryMessage() {
        const container = document.getElementById('galleryContainer');
        container.innerHTML = `
            <div class="col-12" id="emptyGalleryMessage">
                <div class="glass-card text-center text-muted py-5">
                    <i class="bi bi-camera-video-off fs-1 mb-3"></i>
                    <p class="m-0">Belum ada foto yang diunggah ke server.</p>
                </div>
            </div>
        `;
    }

    // Fungsi untuk memfilter foto galeri berdasarkan tanggal via AJAX
    function filterGallery() {
        const filterDate = document.getElementById('galleryFilterDate').value;
        const statusText = document.getElementById('galleryStatusText');

        let url = 'api/get_gallery_photos.php';
        if (filterDate) {
            url += `?date=${filterDate}`;
            statusText.innerHTML = `Tanggal: <span class="text-info fw-bold">${formatIndoDateOnly(filterDate)}</span>`;
        } else {
            statusText.innerText = 'Semua Foto (Limit 100)';
        }

        // Tampilkan loading state
        const container = document.getElementById('galleryContainer');
        container.innerHTML = `
            <div class="col-12 text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-info mb-2" role="status"></div>
                <p class="m-0 small">Memuat data foto...</p>
            </div>
        `;

        fetch(url)
            .then(res => res.json())
            .then(result => {
                if (result.status === 'success') {
                    renderPhotos(result.data);
                } else {
                    container.innerHTML = `<div class="col-12 text-center text-danger py-4">Gagal memuat: ${result.message}</div>`;
                }
            })
            .catch(err => {
                console.error("Error filtering gallery:", err);
                container.innerHTML = `<div class="col-12 text-center text-danger py-4">Koneksi terputus. Gagal memuat foto.</div>`;
            });
    }

    // Fungsi untuk merender daftar foto ke galeri
    function renderPhotos(photos) {
        const container = document.getElementById('galleryContainer');
        container.innerHTML = '';

        if (photos.length === 0) {
            showEmptyGalleryMessage();
            return;
        }

        photos.forEach(photo => {
            const col = document.createElement('div');
            col.className = 'col gallery-card-wrapper';
            col.setAttribute('data-id', photo.id);

            const photoTime = new Date(photo.created_at).toLocaleTimeString('id-ID');
            const photoName = photo.filename.replace('.jpg', '');

            col.innerHTML = `
                <div class="glass-card p-2 gallery-item position-relative" style="cursor: pointer;">
                    <div class="position-absolute top-0 end-0 m-2 select-checkbox-container ${isSelectMode ? '' : 'd-none'}" style="z-index: 10;">
                        <input type="checkbox" class="form-check-input photo-checkbox" value="${photo.id}" style="width: 1.35em; height: 1.35em; cursor: pointer;" onclick="event.stopPropagation(); handleCheckboxChange();">
                    </div>
                    <div class="photo-click-area" onclick="handlePhotoClick(this, event, '${photo.filepath}', '${photo.created_at}')">
                        <img src="${photo.filepath}" alt="${photo.filename}" class="rounded">
                        <div class="gallery-overlay">
                            <span class="fw-medium d-block"><i class="bi bi-calendar"></i> ${photoName}</span>
                            <span class="text-muted small"><i class="bi bi-clock"></i> ${photoTime}</span>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(col);
        });

        // Sembunyikan tombol hapus terpilih dan reset hitungan
        document.getElementById('btnDeleteSelected').classList.add('d-none');
        document.getElementById('selectedCount').innerText = '0';
    }

    // Bersihkan filter tanggal dan tampilkan semua foto kembali
    function clearGalleryFilter() {
        document.getElementById('galleryFilterDate').value = '';
        filterGallery();
    }

    // Fungsi bantu format tanggal Indonesia sederhana untuk filter status (YYYY-MM-DD -> DD mmm YYYY)
    function formatIndoDateOnly(dateStr) {
        if (!dateStr) return "";
        const date = new Date(dateStr);
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // Fungsi polling foto terbaru dari API (Setiap 5 Detik)
    function checkForNewPhoto() {
        // Hentikan auto-prepend jika sedang dalam mode pilih foto atau ada filter aktif
        if (isSelectMode || document.getElementById('galleryFilterDate').value) return;

        fetch('api/get_latest_photo.php')
            .then(res => res.json())
            .then(result => {
                if (result.status === 'success') {
                    const data = result.data;
                    
                    // Jika ada ID foto baru yang lebih besar dari yang kita miliki saat ini
                    if (data.id > latestPhotoId) {
                        latestPhotoId = data.id;

                        // 1. Update Tampilan Utama (Hero Image & Time)
                        document.getElementById('latestHeroImage').src = data.filepath;
                        document.getElementById('latestHeroTime').innerText = formatIndoDate(data.created_at);

                        // 2. Prepend ke Galeri Sejarah di Bawah
                        const galleryContainer = document.getElementById('galleryContainer');
                        
                        // Hapus pesan kosong jika sebelumnya tidak ada foto sama sekali
                        const emptyMsg = document.getElementById('emptyGalleryMessage');
                        if (emptyMsg) {
                            emptyMsg.remove();
                        }

                        // Buat kolom kartu galeri baru
                        const newCol = document.createElement('div');
                        newCol.className = 'col gallery-card-wrapper';
                        newCol.setAttribute('data-id', data.id);
                        
                        // Ekstrak jam menit detik untuk tampilan galeri
                        const photoTime = new Date(data.created_at).toLocaleTimeString('id-ID');
                        const photoName = data.filename.replace('.jpg', '');

                        newCol.innerHTML = `
                            <div class="glass-card p-2 gallery-item position-relative" style="cursor: pointer;">
                                <div class="position-absolute top-0 end-0 m-2 select-checkbox-container d-none" style="z-index: 10;">
                                    <input type="checkbox" class="form-check-input photo-checkbox" value="${data.id}" style="width: 1.35em; height: 1.35em; cursor: pointer;" onclick="event.stopPropagation(); handleCheckboxChange();">
                                </div>
                                <div class="photo-click-area" onclick="handlePhotoClick(this, event, '${data.filepath}', '${data.created_at}')">
                                    <img src="${data.filepath}" alt="${data.filename}" class="rounded">
                                    <div class="gallery-overlay">
                                        <span class="fw-medium d-block"><i class="bi bi-calendar"></i> ${photoName}</span>
                                        <span class="text-muted small"><i class="bi bi-clock"></i> ${photoTime}</span>
                                    </div>
                                </div>
                            </div>
                        `;

                        // Masukkan ke galeri paling atas
                        galleryContainer.insertBefore(newCol, galleryContainer.firstChild);

                        // Batasi galeri maksimal hanya menampilkan 24 item agar tidak membebani browser
                        const cardList = document.querySelectorAll('.gallery-card-wrapper');
                        if (cardList.length > 24) {
                            cardList[cardList.length - 1].remove();
                        }
                    }
                }
            })
            .catch(err => console.error("Error polling new photo:", err));
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Format tanggal awal secara offline
        const heroTime = document.getElementById('latestHeroTime');
        if (heroTime && heroTime.innerText.trim() !== '-') {
            heroTime.innerText = formatIndoDate(heroTime.innerText);
        }

        // Setup interval pengecekan 5 detik
        setInterval(checkForNewPhoto, 5000);
    });
    </script>
</body>
</html>
