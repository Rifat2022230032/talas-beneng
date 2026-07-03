<!-- navbar.php -->
<!-- Header Navigation Top Bar -->
<!-- SweetAlert2 CSS & JS (Global Integration) -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* Force Navbar and Dropdown stacking context above all main content glass-cards */
.top-navbar {
    position: relative !important;
    z-index: 2000 !important;
}
.dropdown {
    position: relative !important;
    z-index: 2010 !important;
}
#notifDropdownMenu {
    z-index: 99999 !important;
}

/* Animasi berdering untuk Bell */
@keyframes bellRing {
    0% { transform: rotate(0); }
    15% { transform: rotate(15deg); }
    30% { transform: rotate(-15deg); }
    45% { transform: rotate(10deg); }
    60% { transform: rotate(-10deg); }
    75% { transform: rotate(4deg); }
    85% { transform: rotate(-4deg); }
    100% { transform: rotate(0); }
}
.bell-ring {
    animation: bellRing 0.8s ease-in-out;
}

/* Custom Scrollbar untuk Dropdown Notifikasi */
#notifListContainer::-webkit-scrollbar {
    width: 6px;
}
#notifListContainer::-webkit-scrollbar-track {
    background: transparent;
}
#notifListContainer::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.1);
    border-radius: 4px;
}
#notifListContainer::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.2);
}

/* Link dropdown item hover */
.notif-item {
    transition: background-color 0.2s ease;
}
.notif-item:hover {
    background-color: rgba(2, 132, 199, 0.05) !important;
}
</style>

<div class="top-navbar">
    <button class="menu-toggle" id="menuToggle">
        <i class="bi bi-list"></i>
    </button>
    <div class="d-none d-md-block">
        <h4 class="m-0 fw-semibold">Monitoring Talas Beneng</h4>
    </div>
    
    <div class="d-flex align-items-center gap-3 ms-auto ms-md-0">
        <!-- Notification Dropdown -->
        <div class="dropdown">
            <a class="position-relative text-dark fs-5 p-2 bg-light bg-opacity-75 rounded-circle d-flex align-items-center justify-content-center" 
               href="#" role="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" 
               style="width: 40px; height: 40px; border: 1px solid var(--border-glass); transition: all 0.3s ease;">
                <i class="bi bi-bell-fill text-muted" id="notifBellIcon"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light d-none" 
                      id="notifCountBadge" style="font-size: 0.65rem; padding: 0.35em 0.5em;">0</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg py-0 border-0 overflow-hidden" 
                aria-labelledby="notifDropdown" id="notifDropdownMenu" 
                style="width: 320px; font-size: 0.85rem; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(15px); border-radius: 14px; border: 1px solid var(--border-glass); z-index: 1050 !important;">
                <li class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                    <span class="fw-bold text-dark"><i class="bi bi-bell"></i> Pemberitahuan</span>
                    <a href="#" onclick="markAllNotificationsAsRead(event)" class="text-info text-decoration-none fw-semibold small">Tandai dibaca</a>
                </li>
                <div id="notifListContainer" style="max-height: 280px; overflow-y: auto;">
                    <li class="p-4 text-center text-muted">Tidak ada pemberitahuan baru</li>
                </div>
                <li class="border-top text-center py-2 bg-light">
                    <a href="notifikasi.php" class="text-secondary text-decoration-none fw-semibold small d-block py-1">Lihat Semua Log</a>
                </li>
            </ul>
        </div>

        <!-- Status Device ESP32 Utama -->
        <div class="status-badge status-offline" id="espStatusBadge">
            <span class="status-pulse"></span>
            <span>IoT: <strong id="espStatusText">Offline</strong></span>
        </div>

        <!-- Status Device ESP32-CAM -->
        <div class="status-badge status-offline" id="camStatusBadge">
            <span class="status-pulse"></span>
            <span>Camera: <strong id="camStatusText">Offline</strong></span>
        </div>
    </div>
</div>

<script>
// Sinkronisasi status koneksi device di header
function updateDeviceStatusBadge() {
    fetch('api/get_latest_sensor.php')
        .then(response => response.json())
        .then(result => {
            const espBadge = document.getElementById('espStatusBadge');
            const espText = document.getElementById('espStatusText');
            const camBadge = document.getElementById('camStatusBadge');
            const camText = document.getElementById('camStatusText');

            if (result.status === 'success') {
                const data = result.data;
                
                // ESP32 Status
                if (data.esp32_status === 'Online') {
                    espBadge.className = 'status-badge status-online';
                    espText.innerText = 'Online';
                } else {
                    espBadge.className = 'status-badge status-offline';
                    espText.innerText = 'Offline';
                }

                // ESP32-CAM Status
                if (data.cam_status === 'Online') {
                    camBadge.className = 'status-badge status-online';
                    camText.innerText = 'Online';
                } else {
                    camBadge.className = 'status-badge status-offline';
                    camText.innerText = 'Offline';
                }
            } else {
                espBadge.className = 'status-badge status-offline';
                espText.innerText = 'Offline';
                camBadge.className = 'status-badge status-offline';
                camText.innerText = 'Offline';
            }
        })
        .catch(err => {
            console.error('Error fetching device connection status:', err);
        });
}

// Global Notifikasi Realtime & Dropdown Handler
let lastSeenNotifId = localStorage.getItem('lastSeenNotifId') ? parseInt(localStorage.getItem('lastSeenNotifId')) : 0;
let isFirstLoad = true;

function checkRealtimeNotifications() {
    fetch('api/get_notifications.php')
        .then(res => res.json())
        .then(result => {
            if (result.status === 'success') {
                const notifications = result.data;
                
                // Filter unread notifications
                const unreadNotifs = notifications.filter(item => item.status === 'unread');
                
                // Update badge di navbar
                const countBadge = document.getElementById('notifCountBadge');
                const bellIcon = document.getElementById('notifBellIcon');
                
                if (unreadNotifs.length > 0) {
                    countBadge.innerText = unreadNotifs.length;
                    countBadge.classList.remove('d-none');
                    bellIcon.classList.add('text-danger');
                    bellIcon.classList.remove('text-muted');
                } else {
                    countBadge.classList.add('d-none');
                    bellIcon.classList.add('text-muted');
                    bellIcon.classList.remove('text-danger');
                }

                // Update badge di sidebar jika elemen ada
                const sidebarBadge = document.getElementById('notifBadge');
                if (sidebarBadge) {
                    if (unreadNotifs.length > 0) {
                        sidebarBadge.innerText = unreadNotifs.length;
                        sidebarBadge.classList.remove('d-none');
                    } else {
                        sidebarBadge.classList.add('d-none');
                    }
                }

                // Render list dropdown notifikasi (max 5 terbaru)
                const listContainer = document.getElementById('notifListContainer');
                if (notifications.length === 0) {
                    listContainer.innerHTML = `<li class="p-4 text-center text-muted">Tidak ada pemberitahuan</li>`;
                } else {
                    let html = '';
                    const recentNotifs = notifications.slice(0, 5);
                    recentNotifs.forEach(item => {
                        let colorClass = 'text-secondary';
                        let iconClass = 'bi-info-circle';
                        
                        if (item.type.toUpperCase() === 'SUCCESS') {
                            colorClass = 'text-success';
                            iconClass = 'bi-check-circle-fill';
                        } else if (item.type.toUpperCase() === 'WARNING') {
                            colorClass = 'text-warning';
                            iconClass = 'bi-exclamation-triangle-fill';
                        } else if (item.type.toUpperCase() === 'ERROR') {
                            colorClass = 'text-danger';
                            iconClass = 'bi-x-circle-fill';
                        } else if (item.type.toUpperCase() === 'INFO') {
                            colorClass = 'text-info';
                            iconClass = 'bi-info-circle-fill';
                        }

                        const isUnread = item.status === 'unread';
                        const itemBg = isUnread ? 'rgba(2, 132, 199, 0.03)' : '#ffffff';

                        html += `
                            <li class="notif-item p-3 border-bottom d-flex align-items-start gap-2" style="background: ${itemBg}; list-style-type: none;">
                                <i class="bi ${iconClass} ${colorClass} fs-5 mt-0.5"></i>
                                <div class="w-100">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong class="${colorClass} text-uppercase fw-semibold" style="font-size:0.75rem;">${item.type}</strong>
                                        <small class="text-muted" style="font-size:0.7rem;">${formatIndoTimeOnly(item.created_at)}</small>
                                    </div>
                                    <p class="m-0 text-dark mt-1 text-wrap" style="line-height:1.4; font-size:0.8rem;">${item.message}</p>
                                </div>
                            </li>
                        `;
                    });
                    listContainer.innerHTML = html;
                }

                // Cek notifikasi baru untuk Toast alert (realtime)
                if (notifications.length > 0) {
                    const currentMaxId = Math.max(...notifications.map(item => parseInt(item.id)));
                    
                    if (isFirstLoad) {
                        // Jika load pertama kali, cukup set id tertinggi agar tidak memunculkan toast lama
                        lastSeenNotifId = currentMaxId;
                        localStorage.setItem('lastSeenNotifId', lastSeenNotifId);
                        isFirstLoad = false;
                    } else if (currentMaxId > lastSeenNotifId) {
                        // Temukan notifikasi baru yang masuk
                        const newItems = notifications.filter(item => parseInt(item.id) > lastSeenNotifId).reverse();
                        
                        // Mainkan efek bel berdering
                        bellIcon.classList.add('bell-ring');
                        setTimeout(() => bellIcon.classList.remove('bell-ring'), 1000);

                        newItems.forEach(item => {
                            let sweetIcon = 'info';
                            if (item.type.toUpperCase() === 'SUCCESS') sweetIcon = 'success';
                            if (item.type.toUpperCase() === 'WARNING') sweetIcon = 'warning';
                            if (item.type.toUpperCase() === 'ERROR') sweetIcon = 'error';

                            // Tampilkan Toast mengapung
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'bottom-end',
                                showConfirmButton: false,
                                timer: 4500,
                                timerProgressBar: true,
                                didOpen: (toast) => {
                                    toast.addEventListener('mouseenter', Swal.stopTimer)
                                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                                }
                            });
                            
                            Toast.fire({
                                icon: sweetIcon,
                                title: item.message
                            });
                        });

                        lastSeenNotifId = currentMaxId;
                        localStorage.setItem('lastSeenNotifId', lastSeenNotifId);
                        
                        // Jika di halaman notifikasi.php, panggil reload data tabel
                        if (typeof loadNotifications === 'function') {
                            loadNotifications();
                        }
                    }
                }
            }
        })
        .catch(err => console.error("Error checking notifications:", err));
}

// Fungsi bantu format jam saja untuk dropdown list
function formatIndoTimeOnly(dateStr) {
    if (!dateStr) return "";
    const date = new Date(dateStr);
    return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

// Tandai semua notifikasi dibaca via AJAX
function markAllNotificationsAsRead(e) {
    if (e) e.preventDefault();
    fetch('api/mark_notifications_read.php', { method: 'POST' })
        .then(res => res.json())
        .then(result => {
            if (result.status === 'success') {
                checkRealtimeNotifications();
                if (typeof loadNotifications === 'function') {
                    loadNotifications();
                }
            }
        })
        .catch(err => console.error("Gagal menandai dibaca:", err));
}

// Jalankan saat load pertama dan interval
document.addEventListener('DOMContentLoaded', function() {
    updateDeviceStatusBadge();
    setInterval(updateDeviceStatusBadge, 2500);

    checkRealtimeNotifications();
    setInterval(checkRealtimeNotifications, 5000);
});
</script>

