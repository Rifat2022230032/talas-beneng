<!-- sidebar.php -->
<!-- Navigasi Sidebar Kiri Sistem Monitoring -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="32" height="32" class="leaf-glow" style="color: var(--primary-leaf); flex-shrink: 0;">
            <path d="M50,10 C50,10 82,38 78,68 C74,88 50,94 50,94 C50,94 26,88 22,68 C18,38 50,10 50,10 Z" fill="rgba(45, 122, 77, 0.15)" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
            <path d="M50,10 L50,94" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
            <path d="M50,35 Q65,30 71,25" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"/>
            <path d="M50,35 Q35,30 29,25" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"/>
            <path d="M50,55 Q68,50 73,45" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"/>
            <path d="M50,55 Q32,50 27,45" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none"/>
            <path d="M50,75 Q68,71 71,67" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" fill="none"/>
            <path d="M50,75 Q32,71 29,67" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" fill="none"/>
        </svg>
        <h5 style="margin-left: 4px;">RUMAH PENGERING</h5>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="index.php">
                <i class="bi bi-house-door"></i>
                <span>Beranda</span>
            </a>
        </li>
        <li>
            <a href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="notifikasi.php" class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-bell"></i>
                    <span>Notifikasi</span>
                </div>
                <span class="badge bg-danger rounded-pill d-none" id="notifBadge" style="font-size: 0.75rem; padding: 0.35em 0.55em;">0</span>
            </a>
        </li>
        <li>
            <a href="foto.php">
                <i class="bi bi-camera"></i>
                <span>Foto Kamera</span>
            </a>
        </li>
        <li>
            <a href="riwayat.php">
                <i class="bi bi-clock-history"></i>
                <span>Riwayat Data</span>
            </a>
        </li>
        <li>
            <a href="setting.php">
                <i class="bi bi-sliders"></i>
                <span>Pengaturan</span>
            </a>
        </li>
    </ul>
</div>
