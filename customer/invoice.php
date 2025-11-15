<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

// Jika admin tidak boleh mengakses halaman ini
if (isAdmin()) {
    redirect('index.php');
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Ambil data transaksi (order) untuk user yang sedang login dan hanya status 'selesai'
$query = "SELECT t.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
          FROM transaksi t
          LEFT JOIN users u ON t.id_user = u.user_id
          WHERE t.id = ? AND t.id_user = ? AND t.status = 'selesai'
          LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    // jika tidak ditemukan, arahkan kembali ke halaman pesanan
    redirect('customer/orders.php');
}

// Ambil semua item untuk transaksi ini
$items_query = "SELECT dt.*, k.nama_kostum, kv.id, kv.id AS id_variasi, lm.nama_layanan, k.foto
                FROM detail_transaksi dt
                LEFT JOIN kostum k ON dt.id_kostum = k.id
                LEFT JOIN kostum_variasi kv ON dt.id_kostum_variasi = kv.id
                LEFT JOIN layanan_makeup lm ON dt.id_layanan_makeup = lm.id
                WHERE dt.id_transaksi = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
$calculated_subtotal = 0;
$has_makeup = false;
$has_kostum = false;
$metode_ambil_for_display = null; // akan diambil dari detail_transaksi (kostum)
while ($row = $items_result->fetch_assoc()) {
    // standardize jumlah & subtotal field names
    $row['jumlah'] = isset($row['jumlah']) ? (int)$row['jumlah'] : (isset($row['jumlah_sewa']) ? (int)$row['jumlah_sewa'] : 1);
    $row['subtotal'] = isset($row['subtotal']) ? (float)$row['subtotal'] : 0.0;

    // flag type berdasarkan kolom id_kostum / id_layanan_makeup
    if (!empty($row['id_kostum'])) {
        $has_kostum = true;
        // simpan metode ambil dari record kostum (ambil pertama yang ditemukan)
        if ($metode_ambil_for_display === null && isset($row['metode_ambil'])) {
            $metode_ambil_for_display = $row['metode_ambil'];
        }
    }
    if (!empty($row['id_layanan_makeup'])) {
        $has_makeup = true;
    }

    $calculated_subtotal += $row['subtotal'];
    $items[] = $row;
}
$stmt->close();

// Ambil alamat & catatan dari salah satu detail_transaksi (jika disimpan per-item)
$address_query = "SELECT alamat, catatan FROM detail_transaksi WHERE id_transaksi = ? LIMIT 1";
$stmt = $conn->prepare($address_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$res_address = $stmt->get_result()->fetch_assoc();
$stmt->close();

$alamat_pengiriman = $res_address['alamat'] ?? ($order['alamat'] ?? '');
$catatan_item = $res_address['catatan'] ?? ($order['catatan'] ?? '');

// Beberapa aplikasi menyimpan biaya pengiriman terpisah, jika ada kolom shipping_cost/harga_ongkir gunakan itu.
// Jika tidak ada, kita asumsikan total_harga sudah termasuk semua (sehingga shipping = 0)
$shipping_cost = 0.0;
if (isset($order['shipping_cost'])) {
    $shipping_cost = (float)$order['shipping_cost'];
} elseif (isset($order['ongkir'])) {
    $shipping_cost = (float)$order['ongkir'];
}

// total yang tercatat di transaksi (di orders.php mereka menampilkan formatRupiah($order['total_harga']))
$total_amount = isset($order['total_harga']) ? (float)$order['total_harga'] : (isset($order['total']) ? (float)$order['total'] : $calculated_subtotal + $shipping_cost);

// helpers: formatDate() dan formatRupiah() diasumsikan sudah tersedia dari includes/config
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo isset($order['id']) ? $order['id'] : ''; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            color: #3E352E;
            background: #f7f5f1;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border: 2px solid #E8DCC8;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #7A6A54;
        }
        
        .company-info h1 {
            color: #7A6A54;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .company-info p {
            color: #666;
            font-size: 14px;
        }
        
        .invoice-info {
            text-align: right;
        }
        
        .invoice-info h2 {
            color: #3E352E;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .invoice-info p {
            font-size: 14px;
            color: #666;
        }
        
        .customer-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .info-section {
            flex: 1;
        }
        
        .info-section h3 {
            color: #7A6A54;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .info-section p {
            font-size: 14px;
            line-height: 1.6;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        thead {
            background-color: #F5EFE7;
        }
        
        th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #3E352E;
            border-bottom: 2px solid #7A6A54;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #E8DCC8;
            color: #666;
        }
        
        .text-right {
            text-align: right;
        }
        
        .totals {
            margin-left: auto;
            width: 300px;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .totals-row.grand-total {
            border-top: 2px solid #7A6A54;
            padding-top: 12px;
            margin-top: 8px;
            font-weight: bold;
            font-size: 18px;
            color: #7A6A54;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E8DCC8;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            background-color: #D1FAE5;
            color: #065F46;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Print Button -->
        <div class="no-print" style="text-align: right; margin-bottom: 20px;">
            <button onclick="window.print()" style="background: #7A6A54; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                Cetak Invoice
            </button>
        </div>
        
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1><?php echo defined('SITE_NAME') ? SITE_NAME : 'TOKO'; ?></h1>
                <p>Jakarta, Indonesia</p>
                <p>Telp: +62 812-3456-789</p>
                <p>Email: info@charitize.com</p>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p><strong>No:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
                <p><strong>Tanggal:</strong> <?php echo function_exists('formatDate') ? formatDate($order['tanggal_pemesanan']) : htmlspecialchars($order['tanggal_pemesanan']); ?></p>
                <p><span class="status-badge">LUNAS</span></p>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="customer-info">
            <div class="info-section">
                <h3>Kepada:</h3>
                <p>
                    <strong><?php echo htmlspecialchars($order['user_name'] ?? $order['nama_pemesan'] ?? 'Pelanggan'); ?></strong><br>
                    <?php echo htmlspecialchars($order['user_email'] ?? $order['email'] ?? ''); ?><br>
                    <?php echo htmlspecialchars($order['user_phone'] ?? $order['phone'] ?? ''); ?>
                </p>
            </div>
            <div class="info-section">
                <?php if ($has_makeup): ?>
                <h3>Alamat Pengiriman:</h3>
                <p>
                    <?php echo nl2br(htmlspecialchars($alamat_pengiriman)); ?><br>
                    <?php
                    if (!empty($order['kota'])) echo htmlspecialchars($order['kota']) . '<br>';
                    if (!empty($order['kode_pos'])) echo 'Kode Pos: ' . htmlspecialchars($order['kode_pos']);
                    ?>
                </p>
                <?php elseif ($has_kostum): ?>
                <h3>Metode Ambil:</h3>
                <p>
                    <strong><?php echo htmlspecialchars($metode_ambil_for_display ?? ($order['metode_ambil'] ?? 'Ambil')); ?></strong><br>
                    <?php
                    // Jika ada tanggal_sewa dan tanggal_selesai di salah satu detail_transaksi, tampilkan ringkasan periode
                    // Ambil jika ada (ambil dari first detail_transaksi yang memiliki tanggal_sewa)
                    $date_stmt = $conn->prepare("SELECT tanggal_sewa, tanggal_selesai FROM detail_transaksi WHERE id_transaksi = ? AND id_kostum IS NOT NULL LIMIT 1");
                    if ($date_stmt) {
                        $date_stmt->bind_param("i", $order_id);
                        $date_stmt->execute();
                        $drow = $date_stmt->get_result()->fetch_assoc();
                        $date_stmt->close();
                        if ($drow) {
                            $t1 = $drow['tanggal_sewa'] ?? null;
                            $t2 = $drow['tanggal_selesai'] ?? null;
                           
                        }
                    }
                    ?>
                </p>
                <?php else: ?>
                <h3>Alamat / Info:</h3>
                <p><?php echo nl2br(htmlspecialchars($alamat_pengiriman)); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th>Produk / Layanan</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php
                            if (!empty($item['nama_kostum'])) {
                                echo htmlspecialchars($item['nama_kostum']);
                                if (!empty($item['nama_variasi'])) echo ' - ' . htmlspecialchars($item['nama_variasi']);
                            } elseif (!empty($item['nama_layanan'])) {
                                echo htmlspecialchars($item['nama_layanan']);
                            } else {
                                echo 'Item';
                            }
                            // jika ada tanggal_sewa / tanggal_selesai untuk kostum tunjukkan
                            if (!empty($item['tanggal_sewa']) || !empty($item['tanggal_selesai'])) {
                                $ts = !empty($item['tanggal_sewa']) ? (function_exists('formatDate') ? formatDate($item['tanggal_sewa']) : htmlspecialchars($item['tanggal_sewa'])) : '-';
                                $te = !empty($item['tanggal_selesai']) ? (function_exists('formatDate') ? formatDate($item['tanggal_selesai']) : htmlspecialchars($item['tanggal_selesai'])) : '-';
                                echo '<br><small>Periode Sewa: ' . $ts . ' sampai ' . $te . '</small>';
                            }
                            // jika ada jadwal untuk makeup tampilkan
                            if (!empty($item['tanggal_layanan'])) {
                                echo '<br><small>Jadwal Makeup: ' . (function_exists('formatDate') ? formatDate($item['tanggal_layanan']) : htmlspecialchars($item['tanggal_layanan'])) . ' ' . (!empty($item['jam_mulai']) ? htmlspecialchars($item['jam_mulai']) : '') . (!empty($item['jam_selesai']) ? ' - '.htmlspecialchars($item['jam_selesai']) : '') . '</small>';
                            }
                            ?>
                        </td>
                        <td class="text-right">
                            <?php
                            // Harga: jika ada harga_kostum atau harga_layanan, gunakan; jika tidak, coba hitung dari subtotal/jumlah
                            if (!empty($item['harga_kostum']) && !empty($item['id_kostum'])) {
                                $harga_display = $item['harga_kostum'];
                            } elseif (!empty($item['harga']) && isset($item['harga'])) {
                                $harga_display = $item['harga'];
                            } else {
                                $harga_display = ($item['jumlah'] > 0) ? ($item['subtotal'] / max(1, $item['jumlah'])) : 0;
                            }
                            echo function_exists('formatRupiah') ? formatRupiah($harga_display) : number_format($harga_display,0,',','.');
                            ?>
                        </td>
                        <td class="text-right">
                            <?php
                            // Jika type kostum: gunakan jumlah dari detail_transaksi (sudah kita simpan di $item['jumlah'])
                            echo intval($item['jumlah']);
                            ?>
                        </td>
                        <td class="text-right"><?php echo function_exists('formatRupiah') ? formatRupiah($item['subtotal']) : number_format($item['subtotal'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Tidak ada item pada pesanan ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="totals">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span><?php echo function_exists('formatRupiah') ? formatRupiah($calculated_subtotal) : number_format($calculated_subtotal,0,',','.'); ?></span>
            </div>
            <div class="totals-row grand-total">
                <span>TOTAL:</span>
                <span><?php echo function_exists('formatRupiah') ? formatRupiah($total_amount) : number_format($total_amount,0,',','.'); ?></span>
            </div>
        </div>
        
        <!-- Tracking Info (jika ada kolom tracking_number atau no_resi) -->
        <?php
        $tracking = $order['tracking_number'] ?? $order['no_resi'] ?? $order['resi'] ?? '';
        if (!empty($tracking)): ?>
        <div style="background: #F5EFE7; padding: 15px; border-radius: 5px; margin-top: 20px;">
            <p style="margin-bottom: 5px; color: #7A6A54; font-weight: bold;">Nomor Resi Pengiriman:</p>
            <p style="font-size: 18px; color: #3E352E; font-weight: bold;"><?php echo htmlspecialchars($tracking); ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Catatan -->
        <?php if (!empty($catatan_item) || !empty($order['catatan']) || !empty($order['catatan_admin'])): ?>
            <div style="margin-top:20px;">
                <?php if (!empty($order['catatan'])): ?>
                    <div style="background:#FFF7ED;padding:12px;border-left:4px solid #F59E0B;margin-bottom:8px;">
                        <strong>Catatan Pembeli:</strong><br>
                        <?php echo nl2br(htmlspecialchars($order['catatan'])); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($order['catatan_admin'])): ?>
                    <div style="background:#EBF8FF;padding:12px;border-left:4px solid #3B82F6;margin-bottom:8px;">
                        <strong>Catatan Admin:</strong><br>
                        <?php echo nl2br(htmlspecialchars($order['catatan_admin'])); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($catatan_item)): ?>
                    <div style="background:#F3F4F6;padding:12px;border-left:4px solid #9CA3AF;">
                        <strong>Catatan Item:</strong><br>
                        <?php echo nl2br(htmlspecialchars($catatan_item)); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p>Terima kasih atas kepercayaan Anda berbelanja di <?php echo defined('SITE_NAME') ? SITE_NAME : 'TOKO'; ?></p>
            <p>Invoice ini dibuat secara otomatis oleh sistem</p>
        </div>
    </div>
</body>
</html>
