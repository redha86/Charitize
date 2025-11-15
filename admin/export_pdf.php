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
 Summary:
 - total_orders: semua transaksi pada periode
 - completed_orders: transaksi dengan status 'selesai'
 - total_revenue: SUM total_harga untuk status 'selesai'
 - avg_order_value: AVG total_harga untuk status 'selesai'
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
 Detailed orders: transaksi dengan status 'selesai' pada periode
 Ambil nama user (COALESCE untuk name/nama)
*/
$query = "SELECT t.*, COALESCE(u.name, u.name, '-') AS customer_name, u.email
          FROM transaksi t
          LEFT JOIN users u ON t.id_user = u.user_id
          WHERE t.status = 'selesai' AND DATE(t.tanggal_pemesanan) BETWEEN ? AND ?
          ORDER BY t.tanggal_pemesanan DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$detailed_orders = $stmt->get_result();
$stmt->close();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; padding: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #3E352E; padding-bottom: 20px; }
        .header h1 { color: #3E352E; font-size: 24px; margin-bottom: 5px; }
        .header p { color: #666; font-size: 14px; }
        .info { margin-bottom: 20px; background-color: #f5f5f5; padding: 15px; border-radius: 5px; }
        .info-row { margin-bottom: 5px; font-size: 14px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background-color: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-label { font-size: 12px; color: #666; margin-bottom: 5px; }
        .stat-value { font-size: 18px; font-weight: bold; color: #3E352E; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; vertical-align: middle; }
        th { background-color: #3E352E; color: white; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 20px; }
        .no-print { display: block !important; }
        @media print { body { padding: 0; } .no-print { display: none !important; } }
        .print-button-container { text-align: right; margin-bottom: 20px; }
        .btn-print { background-color: #7A6A54; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold; }
        .btn-print:hover { background-color: #5c4f3f; }
    </style>
</head>
<body>
    <div class="no-print print-button-container">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Cetak Laporan
        </button>
    </div>

    <div class="header">
        <h1>Laporan Penjualan</h1>
        <p><?php echo htmlspecialchars(SITE_NAME); ?></p>
        <p>Periode: <?php echo formatDate($start_date); ?> - <?php echo formatDate($end_date); ?></p>
    </div>

    <div class="info">
        <div class="info-row"><strong>Periode Laporan:</strong> <?php echo formatDate($start_date); ?> s/d <?php echo formatDate($end_date); ?></div>
        <div class="info-row"><strong>Dicetak pada:</strong> <?php echo date('d F Y H:i:s'); ?></div>
        <div class="info-row"><strong>Oleh:</strong> <?php echo htmlspecialchars($_SESSION['name'] ?? '-'); ?> (<?php echo htmlspecialchars($_SESSION['role'] ?? '-'); ?>)</div>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Total Pesanan</div>
            <div class="stat-value"><?php echo (int)($summary['total_orders'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pesanan Selesai</div>
            <div class="stat-value"><?php echo (int)($summary['completed_orders'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Pendapatan</div>
            <div class="stat-value"><?php echo formatRupiah($summary['total_revenue'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Rata-rata Transaksi</div>
            <div class="stat-value"><?php echo formatRupiah($summary['avg_order_value'] ?? 0); ?></div>
        </div>
    </div>

    <h3 style="margin-top: 30px; margin-bottom: 15px;">Detail Pesanan (Status: Selesai)</h3>
    <table>
        <thead>
            <tr>
                <th>No. Pesanan</th>
                <th>Tanggal</th>
                <th>Pelanggan</th>
                <th>Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($detailed_orders && $detailed_orders->num_rows > 0): ?>
                <?php while ($order = $detailed_orders->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars('#' . ($order['id'] ?? '-')); ?></td>
                    <td><?php echo formatDate($order['tanggal_pemesanan'] ?? $order['updated_at'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($order['customer_name'] ?? '-'); ?></td>
                    <td><?php echo formatRupiah($order['total_harga'] ?? 0); ?></td>
                    <td><?php
                        $st = $order['status'] ?? '';
                        $map = [
                            'pending' => 'Menunggu Pembayaran',
                            'paid' => 'Dibayar',
                            'proses' => 'Diproses',
                            'selesai' => 'Selesai',
                            'batal' => 'Dibatalkan'
                        ];
                        echo htmlspecialchars($map[$st] ?? $st);
                    ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">
                        Tidak ada transaksi selesai pada periode ini
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dibuat otomatis oleh sistem <?php echo htmlspecialchars(SITE_NAME); ?></p>
        <p>Halaman 1 - <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. All rights reserved.</p>
    </div>
</body>
</html>
