// main.js
// Javascript helper global untuk website monitoring IoT Rumah Pengering

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar responsive toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });

        // Klik di luar sidebar untuk menutup di tampilan mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }

    // Penyesuaian class aktif sidebar berdasarkan URL
    const currentPath = window.location.pathname.split('/').pop();
    const menuItems = document.querySelectorAll('.sidebar-menu li');
    menuItems.forEach(item => {
        const link = item.querySelector('a');
        if (link) {
            const href = link.getAttribute('href');
            if (href === currentPath || (currentPath === '' && href === 'index.php')) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        }
    });
});

/**
 * Helper untuk memformat timestamp tanggal menjadi format lokal yang rapi
 * 
 * @param {string} dateStr Format YYYY-MM-DD HH:MM:SS
 * @returns {string} Tanggal terformat Indonesia
 */
function formatIndoDate(dateStr) {
    if (!dateStr) return "-";
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}
