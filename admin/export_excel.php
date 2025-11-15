<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Access denied');
}

$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

$conn = getConnection();

/*
 Summary (menggunakan transaksi)
*/
$query = "SELECT 
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END), 0) AS completed_orders,
            COALESCE(SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END), 0) AS total_revenue,
            COALESCE(AVG(CASE WHEN status = 'selesai' THEN total_harga ELSE NULL END), 0) AS avg_order_value
          FROM transaksi
          WHERE DATE(tanggal_pemesanan) BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

/*
 Sales by date (transaksi)
*/
$query = "SELECT 
            DATE(tanggal_pemesanan) AS order_date,
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN status = 'selesai' THEN total_harga ELSE 0 END), 0) AS revenue
          FROM transaksi
          WHERE DATE(tanggal_pemesanan) BETWEEN ? AND ?
          GROUP BY DATE(tanggal_pemesanan)
          ORDER BY order_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_by_date = $stmt->get_result();
$stmt->close();

/*
 Top products (dari detail_transaksi)
 - Kelompok berdasarkan id_kostum atau id_layanan_makeup
 - Ambil nama dari tabel kostum / layanan_makeup
 - Hanya hitung transaksi yang selesai (status = 'selesai')
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
                ELSE 'Item'
            END AS nama_produk,
            SUM(dt.jumlah) AS total_sold,
            SUM(dt.subtotal) AS total_revenue
          FROM detail_transaksi dt
          JOIN transaksi t ON dt.id_transaksi = t.id
          LEFT JOIN kostum k ON dt.id_kostum = k.id
          LEFT JOIN layanan_makeup lm ON dt.id_layanan_makeup = lm.id
          WHERE t.status = 'selesai' AND DATE(t.tanggal_pemesanan) BETWEEN ? AND ?
          GROUP BY product_key, nama_produk
          ORDER BY total_sold DESC
          LIMIT 10";
$stmt2 = $conn->prepare($query);
$stmt2->bind_param("ss", $start_date, $end_date);
$stmt2->execute();
$top_products = $stmt2->get_result();
$stmt2->close();

/*
 Detailed orders (transaksi selesai)
*/
$query = "SELECT t.*, COALESCE(u.name, u.name, '-') AS customer_name, u.email
          FROM transaksi t
          LEFT JOIN users u ON t.id_user = u.user_id
          WHERE t.status = 'selesai' AND DATE(t.tanggal_pemesanan) BETWEEN ? AND ?
          ORDER BY t.tanggal_pemesanan DESC";
$stmt3 = $conn->prepare($query);
$stmt3->bind_param("ss", $start_date, $end_date);
$stmt3->execute();
$detailed_orders = $stmt3->get_result();
// we'll close $stmt3 later after use

// prepare CSV headers
$filename = 'Laporan_Penjualan_' . $start_date . '_' . $end_date . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM for Excel UTF-8
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

fputcsv($output, ['LAPORAN PENJUALAN']);
fputcsv($output, [htmlspecialchars(SITE_NAME)]);
fputcsv($output, ['Periode: ' . formatDate($start_date) . ' - ' . formatDate($end_date)]);
fputcsv($output, ['Dicetak: ' . date('d F Y H:i:s')]);
fputcsv($output, ['Oleh: ' . htmlspecialchars($_SESSION['name'] ?? '-')]);
fputcsv($output, []);

// Summary
fputcsv($output, ['RINGKASAN PENJUALAN']);
fputcsv($output, ['Total Pesanan', (int)($summary['total_orders'] ?? 0)]);
fputcsv($output, ['Pesanan Selesai', (int)($summary['completed_orders'] ?? 0)]);
fputcsv($output, ['Total Pendapatan', formatRupiah($summary['total_revenue'] ?? 0)]);
fputcsv($output, ['Rata-rata Transaksi', formatRupiah($summary['avg_order_value'] ?? 0)]);
fputcsv($output, []);


// Sales by date
fputcsv($output, ['PENJUALAN PER TANGGAL']);
fputcsv($output, ['Tanggal', 'Jumlah Pesanan', 'Total Pendapatan']);
if ($sales_by_date && $sales_by_date->num_rows > 0) {
    while ($sale = $sales_by_date->fetch_assoc()) {
        fputcsv($output, [
            formatDate($sale['order_date']),
            (int)$sale['total_orders'],
            formatRupiah($sale['revenue'])
        ]);
    }
} else {
    // no rows - optionally skip
}
fputcsv($output, []);

// Top products
fputcsv($output, ['PRODUK TERLARIS']);
fputcsv($output, ['Nama Produk', 'Jumlah Terjual', 'Total Revenue']);
if ($top_products && $top_products->num_rows > 0) {
    while ($product = $top_products->fetch_assoc()) {
        fputcsv($output, [
            $product['nama_produk'] ?? '(Tidak Diketahui)',
            (int)$product['total_sold'] . ' unit',
            formatRupiah($product['total_revenue'] ?? 0)
        ]);
    }
} else {
    // none
}
fputcsv($output, []);

// Detailed orders (selesai)
fputcsv($output, ['DETAIL PESANAN SELESAI']);
fputcsv($output, ['No. Pesanan', 'Tanggal', 'Pelanggan', 'Email', 'Total', 'Status']);
if ($detailed_orders && $detailed_orders->num_rows > 0) {
    while ($order = $detailed_orders->fetch_assoc()) {
        fputcsv($output, [
            '#' . ($order['id'] ?? '-'),
            formatDate($order['tanggal_pemesanan'] ?? ''),
            $order['customer_name'] ?? '-',
            $order['email'] ?? '-',
            formatRupiah($order['total_harga'] ?? 0),
            // map status ke label
            (function($st){
                $map = [
                    'pending' => 'Menunggu Pembayaran',
                    'paid' => 'Dibayar',
                    'proses' => 'Diproses',
                    'selesai' => 'Selesai',
                    'batal' => 'Dibatalkan'
                ];
                return $map[$st] ?? $st;
            })($order['status'] ?? '')
        ]);
    }
} else {
    // no rows
}

fclose($output);

// close statement 3
if (isset($stmt3) && $stmt3 instanceof mysqli_stmt) {
    $stmt3->close();
}

exit;
?>
