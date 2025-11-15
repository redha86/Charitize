<?php
$page_title = "Dashboard Admin";
require_once '../includes/header.php';

requireLogin();
requireAdmin();

$conn = getConnection();

// --------- Statistics ---------
$stats = [];

// Total kostum aktif
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM kostum WHERE status = 'aktif'");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stats['products'] = (int)($res['cnt'] ?? 0);
$stmt->close();

// Total customers (users.role = 'customer')
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM users WHERE role = 'customer'");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stats['customers'] = (int)($res['cnt'] ?? 0);
$stmt->close();

// Pending orders (transaksi.status = 'pending')
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM transaksi WHERE status = 'pending'");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stats['pending_orders'] = (int)($res['cnt'] ?? 0);
$stmt->close();

// Revenue (sum transaksi.total_harga for completed statuses)
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_harga),0) AS total FROM transaksi WHERE status IN ('paid','proses','selesai')");
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stats['revenue'] = (float)($res['total'] ?? 0);
$stmt->close();

// Recent orders (last 5)
$recent_orders = $conn->prepare("SELECT t.*, u.name as customer_name 
                                 FROM transaksi t 
                                 LEFT JOIN users u ON t.id_user = u.user_id
                                 ORDER BY t.tanggal_pemesanan DESC
                                 LIMIT 5");
$recent_orders->execute();
$recent_orders_result = $recent_orders->get_result();
$recent_orders->close();

// Low stock (kostum_variasi stok < 10)
$low_stock = $conn->prepare("SELECT kv.*, k.nama_kostum 
                             FROM kostum_variasi kv
                             JOIN kostum k ON kv.id_kostum = k.id
                             WHERE kv.stok < 10
                             ORDER BY kv.stok ASC
                             LIMIT 5");
$low_stock->execute();
$low_stock_result = $low_stock->get_result();
$low_stock->close();


// --------- Chart Data ---------

// 1) Revenue last 6 months
$revenue_labels = [];
$revenue_values = [];

// build last 6 months labels in format YYYY-MM
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} months"));
    $revenue_labels[] = date('M Y', strtotime($m . "-01"));
    $revenue_values[$m] = 0.0;
}

// Query sums grouped by year-month
$stmt = $conn->prepare("SELECT DATE_FORMAT(tanggal_pemesanan, '%Y-%m') AS ym, COALESCE(SUM(total_harga),0) AS tot
                        FROM transaksi
                        WHERE tanggal_pemesanan >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
                          AND status IN ('paid','proses','selesai')
                        GROUP BY ym
                        ORDER BY ym");
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $ym = $r['ym'];
    if (array_key_exists($ym, $revenue_values)) {
        $revenue_values[$ym] = (float)$r['tot'];
    }
}
$stmt->close();

// prepare ordered arrays
$revenue_data = [];
foreach (array_keys($revenue_values) as $ym) {
    $revenue_data[] = $revenue_values[$ym];
}

// 2) Orders by status
$status_labels = [];
$status_counts = [];
$stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt FROM transaksi GROUP BY status");
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $status_labels[] = ucfirst($r['status']);
    $status_counts[] = (int)$r['cnt'];
}
$stmt->close();

// 3) Top 5 rented costumes (sum jumlah in detail_transaksi grouped by id_kostum)
$top_kostum_labels = [];
$top_kostum_values = [];
$stmt = $conn->prepare("SELECT dt.id_kostum, k.nama_kostum, COALESCE(SUM(dt.jumlah),0) AS total_qty
                        FROM detail_transaksi dt
                        JOIN kostum k ON dt.id_kostum = k.id
                        GROUP BY dt.id_kostum
                        ORDER BY total_qty DESC
                        LIMIT 5");
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $top_kostum_labels[] = $r['nama_kostum'];
    $top_kostum_values[] = (int)$r['total_qty'];
}
$stmt->close();

?>
<!-- Admin Layout -->
<div class="flex">
    <?php require_once '../includes/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 bg-lighter-bg lg:ml-64">
        <div class="p-4 sm:p-6 lg:p-8 pb-8 lg:pb-16">
            <!-- Header -->
            <div class="mb-6 lg:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-accent">Dashboard</h1>
                <p class="text-gray-600 mt-2">Selamat datang, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <!-- Total Kostum Aktif -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Total Kostum Aktif</p>
                            <p class="text-2xl sm:text-3xl font-bold text-accent"><?php echo number_format($stats['products']); ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-primary bg-opacity-10 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-tshirt text-xl sm:text-3xl text-primary"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Customers -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Total Pelanggan</p>
                            <p class="text-2xl sm:text-3xl font-bold text-accent"><?php echo number_format($stats['customers']); ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-secondary bg-opacity-10 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-users text-xl sm:text-3xl text-secondary"></i>
                        </div>
                    </div>
                </div>

                <!-- Pending Orders -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Pesanan Pending</p>
                            <p class="text-2xl sm:text-3xl font-bold text-accent"><?php echo number_format($stats['pending_orders']); ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-clock text-xl sm:text-3xl text-yellow-600"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="bg-white rounded-lg shadow-md p-4 lg:p-6">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Total Pendapatan</p>
                            <p class="text-lg sm:text-2xl font-bold text-accent break-words"><?php echo function_exists('formatRupiah') ? formatRupiah($stats['revenue']) : number_format($stats['revenue'],2,',','.'); ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 ml-2">
                            <i class="fas fa-coins text-xl sm:text-3xl text-green-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                <!-- Left: Recent Orders & Low Stock -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Recent Orders -->
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg sm:text-xl font-bold text-accent">
                                <i class="fas fa-shopping-cart mr-2"></i>Pesanan Terbaru
                            </h3>
                        </div>
                        <div class="p-4 sm:p-6">
                            <?php if ($recent_orders_result->num_rows > 0): ?>
                                <div class="space-y-3 sm:space-y-4">
                                    <?php while ($ro = $recent_orders_result->fetch_assoc()): ?>
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 sm:p-4 border border-beige-dark rounded-lg hover:bg-light-bg transition">
                                            <div class="flex-1 mb-2 sm:mb-0">
                                                <p class="font-bold text-accent text-sm sm:text-base">#<?php echo htmlspecialchars($ro['id']); ?></p>
                                                <p class="text-xs sm:text-sm text-gray-600"><?php echo htmlspecialchars($ro['customer_name'] ?? '-'); ?></p>
                                                <p class="text-xs text-gray-500 mt-1"><?php echo function_exists('formatDate') ? formatDate($ro['tanggal_pemesanan']) : htmlspecialchars($ro['tanggal_pemesanan']); ?></p>
                                            </div>
                                            <div class="flex items-center justify-between sm:flex-col sm:items-end gap-2">
                                                <p class="font-bold text-primary text-sm sm:text-base"><?php echo function_exists('formatRupiah') ? formatRupiah($ro['total_harga']) : number_format($ro['total_harga'],2,',','.'); ?></p>
                                                <span class="badge-status inline-block text-xs px-3 py-1 rounded-full <?php
                                                    $st = $ro['status'] ?? '';
                                                    switch ($st) {
                                                        case 'pending': echo 'bg-yellow-100 text-yellow-700'; break;
                                                        case 'paid': echo 'bg-green-100 text-green-700'; break;
                                                        case 'proses': echo 'bg-indigo-100 text-indigo-700'; break;
                                                        case 'selesai': echo 'bg-green-200 text-green-800'; break;
                                                        case 'batal': echo 'bg-red-100 text-red-700'; break;
                                                        default: echo 'bg-gray-100 text-gray-700';
                                                    }
                                                ?>"><?php echo strtoupper($ro['status'] ?? '-'); ?></span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="mt-4 text-center">
                                    <a href="orders.php" class="text-primary hover:text-accent font-medium text-sm sm:text-base">
                                        Lihat Semua Pesanan <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-gray-500 py-8 text-sm sm:text-base">Belum ada pesanan</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Low Stock -->
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg sm:text-xl font-bold text-accent">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Stok Menipis
                            </h3>
                        </div>
                        <div class="p-4 sm:p-6">
                            <?php if ($low_stock_result->num_rows > 0): ?>
                                <div class="space-y-3 sm:space-y-4">
                                    <?php while ($p = $low_stock_result->fetch_assoc()): ?>
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 sm:p-4 border border-beige-dark rounded-lg">
                                            <div class="flex-1 mb-2 sm:mb-0">
                                                <p class="font-bold text-accent text-sm sm:text-base"><?php echo htmlspecialchars($p['nama_kostum']); ?></p>
                                                <p class="text-xs sm:text-sm text-gray-600">Variasi ID: <?php echo (int)$p['id']; ?></p>
                                            </div>
                                            <div class="text-left sm:text-right">
                                                <span class="px-3 py-1 bg-red-100 text-red-600 rounded-full font-bold text-xs sm:text-sm inline-block">
                                                    Stok: <?php echo (int)$p['stok']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="mt-4 text-center">
                                    <a href="kostum.php" class="text-primary hover:text-accent font-medium text-sm sm:text-base">
                                        Kelola Stok <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-gray-500 py-8 text-sm sm:text-base">Semua stok aman</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right: Charts -->
                <div class="space-y-6">
                    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                        <h4 class="font-bold text-accent mb-4">Pendapatan (6 bulan terakhir)</h4>
                        <canvas id="revenueChart" width="400" height="200"></canvas>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                        <h4 class="font-bold text-accent mb-4">Pesanan menurut Status</h4>
                        <canvas id="statusChart" width="400" height="200"></canvas>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                        <h4 class="font-bold text-accent mb-4">Top 5 Kostum Terbanyak Disewa</h4>
                        <canvas id="topKostumChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Footer -->
<div class="lg:ml-64">
    <?php require_once '../includes/footer.php'; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // data from PHP
    const revenueLabels = <?php echo json_encode($revenue_labels, JSON_UNESCAPED_UNICODE); ?>;
    const revenueData = <?php echo json_encode($revenue_data, JSON_NUMERIC_CHECK); ?>;

    const statusLabels = <?php echo json_encode($status_labels, JSON_UNESCAPED_UNICODE); ?>;
    const statusCounts = <?php echo json_encode($status_counts, JSON_NUMERIC_CHECK); ?>;

    const topKostumLabels = <?php echo json_encode($top_kostum_labels, JSON_UNESCAPED_UNICODE); ?>;
    const topKostumValues = <?php echo json_encode($top_kostum_values, JSON_NUMERIC_CHECK); ?>;

    // Revenue Line Chart
    const ctxRev = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctxRev, {
        type: 'line',
        data: {
            labels: revenueLabels,
            datasets: [{
                label: 'Pendapatan',
                data: revenueData,
                fill: true,
                tension: 0.2,
                borderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            // format number - thousands separator
                            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
                        }
                    }
                }
            }
        }
    });

    // Orders by status (pie)
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'pie',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                borderWidth: 1
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Top Kostum bar
    const ctxTop = document.getElementById('topKostumChart').getContext('2d');
    new Chart(ctxTop, {
        type: 'bar',
        data: {
            labels: topKostumLabels,
            datasets: [{
                label: 'Jumlah Sewa',
                data: topKostumValues,
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true }
            }
        }
    });
</script>
