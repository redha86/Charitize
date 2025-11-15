<?php
$page_title = "Laporan Penjualan - Admin";
require_once '../includes/header.php';

requireLogin();
requireAdmin();

$conn = getConnection();

// input tanggal (default: awal bulan sampai hari ini)
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

/*
 Summary
*/
$query = "SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN status IN ('paid', 'proses', 'selesai') THEN 1 ELSE 0 END), 0) as paid_orders,
            COALESCE(SUM(CASE WHEN status IN ('paid', 'proses', 'selesai') THEN total_harga ELSE 0 END), 0) as total_revenue,
            COALESCE(AVG(CASE WHEN status IN ('paid', 'proses', 'selesai') THEN total_harga ELSE NULL END), 0) as avg_order_value,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_orders,
            COALESCE(SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END), 0) as cancelled_orders
          FROM transaksi
          WHERE DATE(tanggal_pemesanan) BETWEEN ? AND ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("REPORT_SUMMARY_PREPARE_ERR: " . $conn->error);
    die("Gagal menyiapkan query summary.");
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

/*
 Sales by date
*/
$query = "SELECT 
            DATE(tanggal_pemesanan) as order_date,
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN status IN ('paid', 'proses', 'selesai') THEN total_harga ELSE 0 END), 0) as revenue,
            COALESCE(SUM(CASE WHEN status IN ('paid', 'proses', 'selesai') THEN 1 ELSE 0 END), 0) as successful_orders
          FROM transaksi
          WHERE DATE(tanggal_pemesanan) BETWEEN ? AND ?
          GROUP BY DATE(tanggal_pemesanan)
          ORDER BY order_date DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("REPORT_SALES_BY_DATE_PREPARE_ERR: " . $conn->error);
    die("Gagal menyiapkan query sales by date.");
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_by_date = $stmt->get_result();
$stmt->close();

/*
 Top products
 - Hitungan berdasarkan detail_transaksi (jumlah unit & subtotal)
 - Gabungkan dengan nama_kostum / nama_layanan jika tersedia
*/
$query = "SELECT 
            CASE 
                WHEN dt.id_kostum IS NOT NULL THEN CONCAT('KOSTUM_', dt.id_kostum)
                WHEN dt.id_layanan_makeup IS NOT NULL THEN CONCAT('MAKEUP_', dt.id_layanan_makeup)
                ELSE CONCAT('ITEM_', dt.id)
            END AS product_key,
            CASE 
                WHEN dt.id_kostum IS NOT NULL THEN COALESCE(k.nama_kostum, '(Tidak Diketahui)')
                WHEN dt.id_layanan_makeup IS NOT NULL THEN COALESCE(lm.nama_layanan, '(Tidak Diketahui)')
                ELSE '(Item)'
            END AS product_name,
            SUM(dt.jumlah) AS total_sold,
            COALESCE(SUM(dt.subtotal),0) AS total_revenue
          FROM detail_transaksi dt
          JOIN transaksi t ON dt.id_transaksi = t.id
          LEFT JOIN kostum k ON dt.id_kostum = k.id
          LEFT JOIN layanan_makeup lm ON dt.id_layanan_makeup = lm.id
          WHERE t.status IN ('paid', 'proses', 'selesai') AND DATE(t.tanggal_pemesanan) BETWEEN ? AND ?
          GROUP BY product_key, product_name
          ORDER BY total_sold DESC
          LIMIT 10";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("REPORT_TOP_PRODUCTS_PREPARE_ERR: " . $conn->error);
    die("Gagal menyiapkan query top products.");
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_products = $stmt->get_result();
$stmt->close();

/*
 Detailed orders
 - Mengambil transaksi + info customer + payment terakhir (jika ada)
*/
$query = "SELECT 
            t.*,
            COALESCE(u.name, '-') AS customer_name,
            u.email,
            -- ambil data pembayaran terbaru (subquery)
            (SELECT metode_pembayaran FROM pembayaran WHERE id_transaksi = t.id ORDER BY tanggal_bayar DESC LIMIT 1) AS metode_pembayaran,
            (SELECT jumlah_bayar FROM pembayaran WHERE id_transaksi = t.id ORDER BY tanggal_bayar DESC LIMIT 1) AS jumlah_bayar,
            (SELECT status_pembayaran FROM pembayaran WHERE id_transaksi = t.id ORDER BY tanggal_bayar DESC LIMIT 1) AS status_pembayaran,
            (SELECT tanggal_bayar FROM pembayaran WHERE id_transaksi = t.id ORDER BY tanggal_bayar DESC LIMIT 1) AS tanggal_bayar
          FROM transaksi t
          LEFT JOIN users u ON t.id_user = u.user_id
          WHERE t.status IN ('paid', 'proses', 'selesai') AND DATE(t.tanggal_pemesanan) BETWEEN ? AND ?
          ORDER BY t.tanggal_pemesanan DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("REPORT_DETAILED_ORDERS_PREPARE_ERR: " . $conn->error);
    die("Gagal menyiapkan query detailed orders.");
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$detailed_orders = $stmt->get_result();
$stmt->close();
?>

<div class="flex">
    <!-- Sidebar -->
    <?php require_once '../includes/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 bg-lighter-bg min-h-screen lg:ml-64">
        <div class="p-4 sm:p-6 lg:p-8 pb-20 lg:pb-16">
            <!-- Header -->
            <div class="mb-6 lg:mb-8">
                <h1 class="text-2xl sm:text-3xl font-bold text-accent">Laporan Penjualan</h1>
                <p class="text-gray-600 mt-2 text-sm sm:text-base">Analisis dan laporan penjualan toko</p>
            </div>

            <!-- Filter Form -->
            <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6 lg:mb-8">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="block text-accent font-medium mb-2 text-xs sm:text-sm">Tanggal Mulai</label>
                            <input type="date" name="start_date" 
                                   value="<?php echo htmlspecialchars($start_date); ?>"
                                   class="form-control-custom w-full text-xs sm:text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-accent font-medium mb-2 text-xs sm:text-sm">Tanggal Akhir</label>
                            <input type="date" name="end_date" 
                                   value="<?php echo htmlspecialchars($end_date); ?>"
                                   class="form-control-custom w-full text-xs sm:text-sm">
                        </div>
                        
                        <button type="submit" class="w-full btn-primary-custom px-4 py-3 rounded-lg text-xs sm:text-sm">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>

                        <div class="grid grid-cols-2 gap-2">
                            <a href="export_pdf.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
                               target="_blank" 
                               class="w-full btn-secondary-custom px-3 py-3 rounded-lg inline-block text-center text-xs sm:text-sm">
                                <i class="fas fa-file-pdf mr-1"></i>PDF
                            </a>
                            <a href="export_excel.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" 
                               class="w-full bg-green-600 hover:bg-green-700 text-white px-3 py-3 rounded-lg font-medium transition inline-block text-center text-xs sm:text-sm">
                                <i class="fas fa-file-excel mr-1"></i>Excel
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary Statistics  -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6 mb-6 lg:mb-8">
                <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Total Pesanan</p>
                            <p class="text-2xl sm:text-3xl font-bold text-accent"><?php echo (int)$summary['total_orders']; ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 ml-2">
                            <i class="fas fa-shopping-cart text-xl sm:text-3xl text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Sudah Bayar</p>
                            <p class="text-2xl sm:text-3xl font-bold text-accent"><?php echo (int)$summary['paid_orders']; ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                Pending: <?php echo (int)$summary['pending_orders']; ?> | 
                                Batal: <?php echo (int)$summary['cancelled_orders']; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 ml-2">
                            <i class="fas fa-check-circle text-xl sm:text-3xl text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Total Pendapatan</p>
                            <p class="text-base sm:text-xl font-bold text-accent break-words"><?php echo formatRupiah($summary['total_revenue']); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Dari <?php echo (int)$summary['paid_orders']; ?> pesanan</p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-primary bg-opacity-10 rounded-full flex items-center justify-center flex-shrink-0 ml-2">
                            <i class="fas fa-money-bill-wave text-xl sm:text-3xl text-primary"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-600 text-xs sm:text-sm mb-1">Rata-rata Transaksi</p>
                            <p class="text-sm sm:text-lg font-bold text-accent break-words"><?php echo formatRupiah($summary['avg_order_value']); ?></p>
                        </div>
                        <div class="w-12 h-12 sm:w-16 sm:h-16 bg-secondary bg-opacity-10 rounded-full flex items-center justify-center flex-shrink-0 ml-2">
                            <i class="fas fa-chart-line text-xl sm:text-3xl text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales by Date -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 lg:mb-8">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg sm:text-xl font-bold text-accent">
                        <i class="fas fa-calendar mr-2"></i>Penjualan per Tanggal
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1">Data transaksi terfilter (paid/proses/selesai dihitung sebagai berhasil)</p>
                </div>
                <div class="p-4 sm:p-6 max-h-96 overflow-y-auto">
                    <?php if ($sales_by_date && $sales_by_date->num_rows > 0): ?>
                        <div class="space-y-3">
                            <?php while ($sale = $sales_by_date->fetch_assoc()): ?>
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-3 bg-light-bg rounded-lg gap-2">
                                    <div>
                                        <p class="font-bold text-accent text-sm sm:text-base"><?php echo formatDate($sale['order_date']); ?></p>
                                        <p class="text-xs sm:text-sm text-gray-600">
                                            <?php echo (int)$sale['successful_orders']; ?> transaksi berhasil
                                            (Total: <?php echo (int)$sale['total_orders']; ?>)
                                        </p>
                                    </div>
                                    <p class="text-base sm:text-xl font-bold text-primary whitespace-nowrap"><?php echo formatRupiah($sale['revenue']); ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8 text-sm">Tidak ada data penjualan</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Products -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6 lg:mb-8">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg sm:text-xl font-bold text-accent">
                        <i class="fas fa-star mr-2"></i>Produk Terlaris
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1">Berdasarkan jumlah unit terjual (periode terpilih)</p>
                </div>
                <div class="p-4 sm:p-6 max-h-96 overflow-y-auto">
                    <?php if ($top_products && $top_products->num_rows > 0): ?>
                        <div class="space-y-3">
                            <?php $rank = 1; while ($product = $top_products->fetch_assoc()): ?>
                                <div class="flex items-center gap-3 p-3 bg-light-bg rounded-lg">
                                    <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">
                                        <?php echo $rank++; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-accent text-xs sm:text-sm truncate">
                                            <?php echo htmlspecialchars($product['product_name'] ?? '(Tidak Diketahui)'); ?>
                                        </p>
                                        <p class="text-xs text-gray-600">Terjual: <?php echo (int)$product['total_sold']; ?> unit</p>
                                    </div>
                                    <p class="font-bold text-primary text-xs sm:text-sm whitespace-nowrap"><?php echo formatRupiah($product['total_revenue']); ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-500 py-8 text-sm">Tidak ada data produk</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detailed Orders -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-4 sm:px-6 py-4 border-gray-200">
                    <h3 class="text-lg sm:text-xl font-bold text-accent">
                        <i class="fas fa-list mr-2"></i>Detail Pesanan
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1">Status: Dibayar, Diproses, Selesai</p>
                </div>

                <!-- Mobile Card View -->
                <div class="block lg:hidden">
                    <?php if ($detailed_orders && $detailed_orders->num_rows > 0): ?>
                        <?php 
                        $detailed_orders->data_seek(0);
                        while ($order = $detailed_orders->fetch_assoc()): 
                        ?>
                            <div class="border-b border-gray-200 p-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-bold text-accent text-sm">Order #<?php echo htmlspecialchars($order['id'] ?? '-'); ?></p>
                                            <p class="text-xs text-gray-600"><?php echo formatDate($order['tanggal_pemesanan']); ?></p>
                                        </div>
                                        <span class="badge-status badge-<?php echo htmlspecialchars($order['status'] ?? '-'); ?> text-xs">
                                            <?php echo ORDER_STATUS[$order['status']] ?? htmlspecialchars($order['status'] ?? '-'); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($order['customer_name'] ?? '-'); ?></p>
                                        <p class="text-base font-bold text-primary mt-1"><?php echo formatRupiah($order['total_harga']); ?></p>
                                        <?php if (!empty($order['metode_pembayaran'])): ?>
                                            <p class="text-xs text-gray-500 mt-1">Pembayaran: <?php echo htmlspecialchars($order['metode_pembayaran']); ?> (<?php echo htmlspecialchars($order['status_pembayaran'] ?? '-'); ?>)</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500 text-sm">
                            Tidak ada transaksi pada periode ini
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Desktop Table View (scroll hanya pada tabel tanpa merusak layout) -->
                <div class="hidden lg:block">
                    <!-- outer container punya overflow-x:auto sehingga hanya area ini yang scroll -->
                    <div style="overflow-x:auto; width:100%; -webkit-overflow-scrolling:touch;">
                        <!-- inner block akan menentukan lebar minimum tabel, tanpa memaksa parent melebar -->
                        <div style="min-width:940px; display:inline-block;">
                            <table class="table-custom w-full text-xs sm:text-sm" style="min-width:940px; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th class="text-xs sm:text-sm">No. Pesanan</th>
                                        <th class="text-xs sm:text-sm">Tanggal</th>
                                        <th class="text-xs sm:text-sm">Pelanggan</th>
                                        <th class="text-xs sm:text-sm">Status</th>
                                        <th class="text-xs sm:text-sm">Total</th>
                                        <th class="text-xs sm:text-sm">Pembayaran Terakhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $detailed_orders->data_seek(0);
                                    if ($detailed_orders && $detailed_orders->num_rows > 0): 
                                    ?>
                                        <?php while ($order = $detailed_orders->fetch_assoc()): ?>
                                        <tr>
                                            <td class="font-medium">#<?php echo htmlspecialchars($order['id'] ?? '-'); ?></td>
                                            <td><?php echo formatDate($order['tanggal_pemesanan']); ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge-status badge-<?php echo htmlspecialchars($order['status'] ?? '-'); ?> text-xs">
                                                    <?php echo ORDER_STATUS[$order['status']] ?? htmlspecialchars($order['status'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td class="font-bold text-primary whitespace-nowrap"><?php echo formatRupiah($order['total_harga']); ?></td>
                                            <td class="whitespace-nowrap">
                                                <?php if (!empty($order['metode_pembayaran'])): ?>
                                                    <div class="text-xs">
                                                        <div><?php echo htmlspecialchars($order['metode_pembayaran']); ?> — <?php echo formatRupiah($order['jumlah_bayar'] ?? 0); ?></div>
                                                        <div class="text-gray-500 text-xs"><?php echo !empty($order['tanggal_bayar']) ? formatDate($order['tanggal_bayar']) : '-'; ?> (<?php echo htmlspecialchars($order['status_pembayaran'] ?? '-'); ?>)</div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-500">Tidak ada pembayaran</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-6 sm:py-8 text-gray-500 text-xs sm:text-sm">
                                                Tidak ada transaksi pada periode ini
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="lg:ml-64">
    <?php require_once '../includes/footer.php'; ?>
</div>
