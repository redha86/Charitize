<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Mobile Menu Button -->
<button id="mobileSidebarToggle" class="lg:hidden fixed top-4 right-4 z-40 bg-accent text-white p-3 rounded-lg shadow-lg hover:bg-primary transition-colors">
    <i class="fas fa-bars text-xl"></i>
</button>

<!-- Overlay untuk mobile -->
<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"></div>

<!-- Sidebar Admin -->
<aside id="adminSidebar" class="sidebar-admin w-64 h-screen fixed transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out z-50">
    <div class="px-6 py-8 h-full overflow-y-auto">
        <!-- Close button untuk mobile -->
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-2xl font-bold text-white">
                <i class="fas fa-tachometer-alt mr-2"></i>Admin Panel
            </h2>
            <button id="closeSidebar" class="lg:hidden text-white hover:text-gray-300 p-2 -mr-2">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <nav class="space-y-2">
            <a href="index.php" class="sidebar-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home mr-3"></i>Dashboard
            </a>
            <a href="kostum.php" class="sidebar-link <?php echo $current_page == 'kostum.php' ? 'active' : ''; ?>">
                <i class="fas fa-box mr-3"></i>Kelola Kostum
            </a>
            <a href="layanan_makeup.php" class="sidebar-link <?php echo $current_page == 'layanan_makeup.php' ? 'active' : ''; ?>">
                <i class="fas fa-warehouse mr-3"></i>Kelola Makeup
            </a>
            <a href="orders.php" class="sidebar-link <?php echo $current_page == 'orders.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart mr-3"></i>Kelola Pesanan
            </a>
            <a href="users.php" class="sidebar-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users mr-3"></i>Kelola Pengguna
            </a>
            <a href="reports.php" class="sidebar-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar mr-3"></i>Laporan Penjualan
            </a>
            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="sidebar-link">
                <i class="fas fa-sign-out-alt mr-3"></i>Logout
            </a>
        </nav>
    </div>
</aside>

<script>
const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
const closeSidebar = document.getElementById('closeSidebar');
const adminSidebar = document.getElementById('adminSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    adminSidebar.classList.remove('-translate-x-full');
    sidebarOverlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden'; 
}

function closeSidebarMenu() {
    adminSidebar.classList.add('-translate-x-full');
    sidebarOverlay.classList.add('hidden');
    document.body.style.overflow = ''; 
}

if (mobileSidebarToggle) {
    mobileSidebarToggle.addEventListener('click', openSidebar);
}

if (closeSidebar) {
    closeSidebar.addEventListener('click', closeSidebarMenu);
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebarMenu);
}

const sidebarLinks = document.querySelectorAll('.sidebar-link');
sidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 1024) { 
            closeSidebarMenu();
        }
    });
});

window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        closeSidebarMenu();
    }
});
</script>