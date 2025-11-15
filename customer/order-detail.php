<?php
$page_title = "Detail Pesanan";
require_once '../includes/header.php';
require_once '../includes/navbar.php';

requireLogin();

if (isAdmin()) {
    redirect('index.php');
}

$conn = getConnection();
$user_id = $_SESSION['user_id'];

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id == 0) {
    $_SESSION['error_message'] = 'Order ID tidak valid';
    redirect('customer/orders.php');
}

// Ambil data transaksi dari tabel `transaksi`
$query = "SELECT * FROM transaksi WHERE id = ? AND id_user = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaction) {
    $_SESSION['error_message'] = 'Pesanan tidak ditemukan';
    redirect('customer/orders.php');
}

/**
 * Helper: build image url
 * - Jika $path kosong -> return placeholder
 * - Jika $path sudah URL absolut -> return langsung
 * - Jika $path relatif -> prefix dengan BASE_URL
 */
function build_image_url($path, $default = 'https://via.placeholder.com/300x300?text=No+Image') {
    if (empty($path)) return $default;
    if (preg_match('/^https?:\/\//i', $path)) return $path;
    // normalisasi
    $base = rtrim(BASE_URL, '/');
    $p = ltrim($path, '/');
    return $base . '/' . $p;
}

// Ambil item detail dari tabel `detail_transaksi` (ambil hanya 1 foto untuk layanan makeup)
$items_query = "
    SELECT dt.*,
           k.nama_kostum,
           k.foto AS foto_kostum,
           kv.id AS id_variasi,
           kv.ukuran AS nama_variasi,
           lm.nama_layanan,
           -- subquery: ambil 1 foto pertama (ordered by id) dari tabel foto_layanan_makeup
           (SELECT flm.path_foto
            FROM foto_layanan_makeup flm
            WHERE flm.id_layanan_makeup = lm.id
              AND flm.path_foto IS NOT NULL
            ORDER BY flm.id ASC
            LIMIT 1) AS foto_makeup
    FROM detail_transaksi dt
    LEFT JOIN kostum k ON dt.id_kostum = k.id
    LEFT JOIN kostum_variasi kv ON dt.id_kostum_variasi = kv.id
    LEFT JOIN layanan_makeup lm ON dt.id_layanan_makeup = lm.id
    WHERE dt.id_transaksi = ?
";
$stmt = $conn->prepare($items_query);
if (!$stmt) {
    // debug cepat jika terjadi error prepare
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param("i", $transaction['id']);
$stmt->execute();
$items_result = $stmt->get_result();
$items_arr = $items_result->fetch_all(MYSQLI_ASSOC); // ambil sebagai array supaya bisa dipakai ulang
$stmt->close();

// Ambil alamat / catatan dari salah satu detail_transaksi (jika disimpan per-item)
$address = '';
$detail_note = '';
$addr_query = "SELECT alamat, catatan FROM detail_transaksi WHERE id_transaksi = ? LIMIT 1";
$stmt = $conn->prepare($addr_query);
$stmt->bind_param("i", $transaction['id']);
$stmt->execute();
$res_addr = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($res_addr) {
    $address = $res_addr['alamat'] ?? '';
    $detail_note = $res_addr['catatan'] ?? '';
}

// Ambil data pembayaran (jika ada) — ambil yang terbaru
$payment = null;
$pay_query = "SELECT * FROM pembayaran WHERE id_transaksi = ? ORDER BY tanggal_bayar DESC LIMIT 1";
$stmt = $conn->prepare($pay_query);
$stmt->bind_param("i", $transaction['id']);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Map status (fallback jika ORDER_STATUS tidak tersedia) -- tambahkan 'ditolak'
$status_map = [
    'pending' => 'Menunggu Pembayaran',
    'proses'  => 'Diproses',
    'selesai' => 'Selesai',
    'batal'   => 'Dibatalkan',
    'ditolak' => 'Pembayaran Ditolak'
];
$status_label = $status_map[$transaction['status']] ?? $transaction['status'];

// Ambil data user (nama & nohp) dari tabel users sesuai permintaan
$user_query = "SELECT name, phone FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="<?php echo BASE_URL; ?>customer/orders.php" class="inline-flex items-center text-primary hover:text-accent transition">
            <i class="fas fa-arrow-left mr-2"></i>
            <span class="font-medium">Kembali ke Daftar Pesanan</span>
        </a>
    </div>

    <!-- Order Header -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="bg-primary px-6 py-6 text-white">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold mb-2">
                        <i class="fas fa-receipt mr-2"></i>Detail Pesanan
                    </h1>
                    <p class="text-lg opacity-90">Order #<?php echo htmlspecialchars($transaction['id']); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm opacity-100 mb-4">Status Pesanan</p>
                    <span class="badge-status badge-<?php echo htmlspecialchars($transaction['status']); ?> text-lg">
                        <?php echo htmlspecialchars($status_label); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Order Date -->
                <div class="flex items-start gap-3">
                    <div class="w-12 h-12 bg-light-bg rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-calendar text-primary text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Tanggal Pesanan</p>
                        <p class="font-bold text-accent"><?php echo formatDate($transaction['tanggal_pemesanan']); ?></p>
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="flex items-start gap-3">
                    <div class="w-12 h-12 bg-light-bg rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-money-bill-wave text-primary text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Pembayaran</p>
                        <p class="font-bold text-primary text-xl"><?php echo formatRupiah($transaction['total_harga']); ?></p>
                    </div>
                </div>

                <!-- Payment Method (dari tabel pembayaran jika ada) -->
                <div class="flex items-start gap-3">
                    <div class="w-12 h-12 bg-light-bg rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-credit-card text-primary text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Metode Pembayaran</p>
                        <p class="font-bold text-accent">
                            <?php echo !empty($payment['metode_pembayaran']) ? htmlspecialchars($payment['metode_pembayaran']) : 'Transfer / Belum diisi'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order Items & Shipping -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-light-bg px-6 py-4 border-b border-beige-dark">
                    <h2 class="text-xl font-bold text-accent">
                        <i class="fas fa-shopping-bag mr-2"></i>Item yang Dipesan
                    </h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php if (!empty($items_arr) && count($items_arr) > 0): ?>
                            <?php foreach ($items_arr as $item): ?>
                                <div class="flex gap-4 pb-4 border-b border-gray-100 last:border-0">
                                    <!-- Pilih foto berdasarkan transaksi.type -->
                                    <div class="w-24 h-24 flex-shrink-0 bg-light-bg rounded-lg overflow-hidden">
                                        <?php
                                        $img_src = '';
                                        $type = strtolower(trim($transaction['type'] ?? ''));

                                        // jika type kostum -> gunakan foto_kostum
                                        if ($type === 'kostum') {
                                            if (!empty($item['foto_kostum'])) {
                                                $p = $item['foto_kostum'];
                                                // jika hanya filename tanpa path
                                                if (strpos($p, '/') === false) {
                                                    $p = 'assets/images/kostum/' . ltrim($p, '/');
                                                }
                                                $img_src = build_image_url($p);
                                            }
                                        }
                                        // jika type makeup -> gunakan foto_makeup (path_foto)
                                        if (empty($img_src) && $type === 'makeup') {
                                            if (!empty($item['foto_makeup'])) {
                                                $p = $item['foto_makeup'];
                                                // jika path looks like filename only, tambahkan folder default
                                                if (strpos($p, '/') === false) {
                                                    $p = 'assets/images/makeup/' . ltrim($p, '/');
                                                }
                                                $img_src = build_image_url($p);
                                            }
                                        }

                                        // fallback: jika tidak ada sesuai type, gunakan foto_kostum atau foto_makeup bila ada
                                        if (empty($img_src)) {
                                            if (!empty($item['foto_kostum'])) {
                                                $p = $item['foto_kostum'];
                                                if (strpos($p, '/') === false) {
                                                    $p = 'assets/images/kostum/' . ltrim($p, '/');
                                                }
                                                $img_src = build_image_url($p);
                                            } elseif (!empty($item['foto_makeup'])) {
                                                $p = $item['foto_makeup'];
                                                if (strpos($p, '/') === false) {
                                                    $p = 'assets/images/makeup/' . ltrim($p, '/');
                                                }
                                                $img_src = build_image_url($p);
                                            } else {
                                                $img_src = 'https://via.placeholder.com/300x300?text=No+Image';
                                            }
                                        }
                                        ?>
                                        <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($item['nama_kostum'] ?? $item['nama_layanan'] ?? 'Item'); ?>" class="w-full h-full object-cover">
                                    </div>

                                    <!-- Details -->
                                    <div class="flex-1">
                                        <h3 class="font-bold text-accent text-lg mb-1">
                                            <?php
                                            // Tampilkan nama kostum + variasi atau nama layanan
                                            if (!empty($item['id_kostum']) && !empty($item['nama_kostum'])) {
                                                echo htmlspecialchars($item['nama_kostum'] . (!empty($item['nama_variasi']) ? ' - ' . $item['nama_variasi'] : ''));
                                            } elseif (!empty($item['id_layanan_makeup']) && !empty($item['nama_layanan'])) {
                                                echo htmlspecialchars($item['nama_layanan']);
                                            } else {
                                                echo 'Item';
                                            }
                                            ?>
                                        </h3>

                                        <div class="flex items-center gap-4 text-sm text-gray-600 mb-2">
                                            <span>
                                                <i class="fas fa-times mr-1"></i>
                                                <?php echo intval($item['jumlah']); ?> unit
                                            </span>

                                            <span>
                                                <i class="fas fa-tag mr-1"></i>
                                                <?php
                                                // Harga per unit (jika tersedia hitung dari subtotal/jumlah)
                                                $unitPrice = null;
                                                if (!empty($item['subtotal']) && !empty($item['jumlah']) && $item['jumlah'] > 0) {
                                                    $unitPrice = $item['subtotal'] / $item['jumlah'];
                                                }
                                                echo $unitPrice !== null ? formatRupiah($unitPrice) : '-';
                                                ?>
                                            </span>

                                            <?php if (!empty($item['tanggal_sewa'])): ?>
                                                <span> | Tanggal Sewa: <?php echo formatDate($item['tanggal_sewa']); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-sm text-gray-600">Subtotal</p>
                                            <p class="text-xl font-bold text-primary">
                                                <?php echo formatRupiah($item['subtotal']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-gray-600">Tidak ada item pada pesanan ini.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Order Summary -->
                    <div class="mt-6 pt-6 border-t-2 border-beige-dark">
                        <div class="space-y-2">
                            <div class="flex justify-between text-gray-700">
                                <span>Subtotal Produk</span>
                                <span class="font-medium">
                                    <?php
                                    // Hitung subtotal produk dari detail_transaksi jika ingin akurat
                                    $subTotal = 0;
                                    $q = $conn->prepare("SELECT COALESCE(SUM(subtotal),0) as ssum FROM detail_transaksi WHERE id_transaksi = ?");
                                    $q->bind_param("i", $transaction['id']);
                                    $q->execute();
                                    $r = $q->get_result()->fetch_assoc();
                                    $subTotal = $r['ssum'] ?? 0;
                                    $q->close();
                                    echo formatRupiah($subTotal);
                                    ?>
                                </span>
                            </div>

                            <!-- Jika tidak ada shipping_cost di schema, kita hilangkan baris ongkir -->
                            <div class="flex justify-between items-center pt-3 border-t border-gray-200">
                                <span class="text-lg font-bold text-accent">Total Pembayaran</span>
                                <span class="text-2xl font-bold text-primary">
                                    <?php echo formatRupiah($transaction['total_harga']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping / Alamat -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-light-bg px-6 py-4 border-b border-beige-dark">
                    <h2 class="text-xl font-bold text-accent">
                        <i class="fas fa-truck mr-2"></i>Informasi Pemesan
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Penerima -->
                        <div>
                            <h3 class="font-bold text-accent mb-3 flex items-center">
                                <i class="fas fa-user w-6"></i>Penerima
                            </h3>
                            <p class="text-gray-700 font-medium">
                                <?php echo htmlspecialchars($user_data['name'] ?? '-'); ?>
                            </p>
                            <p class="text-gray-600 text-sm mt-1">
                                <i class="fas fa-phone w-6"></i>
                                <?php echo htmlspecialchars($user_data['phone'] ?? '-'); ?>
                            </p>
                        </div>

                        <!-- Alamat (ambil dari detail_transaksi jika ada) -->
                        <div>
                            <h3 class="font-bold text-accent mb-3 flex items-center">
                                <i class="fas fa-map-marker-alt w-6"></i>Alamat / Catatan Item
                            </h3>
                            <p class="text-gray-700">
                                <?php echo !empty($address) ? nl2br(htmlspecialchars($address)) : '-'; ?>
                            </p>
                            <?php if (!empty($detail_note)): ?>
                                <p class="text-gray-600 mt-2"><strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($detail_note)); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Catatan admin/per-order jika ada (jika disimpan di transaksi) -->
                    <?php if (!empty($transaction['catatan_admin'])): ?>
                        <div class="mt-6 bg-blue-50 border-l-4 border-blue-500 p-3 rounded">
                            <p class="text-sm text-blue-700"><strong>Catatan Admin (Pesanan):</strong> <?php echo htmlspecialchars($transaction['catatan_admin']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- NEW: Catatan Admin (Per Item) -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-light-bg px-6 py-4 border-b border-beige-dark">
                    <h2 class="text-xl font-bold text-accent">
                        <i class="fas fa-sticky-note mr-2"></i>Catatan Admin (Per Item)
                    </h2>
                </div>
                <div class="p-6">
                    <?php
                    // Kumpulkan catatan admin non-empty dari detail_transaksi
                    $adminNotes = [];
                    foreach ($items_arr as $it) {
                        $note = trim($it['catatan_admin'] ?? '');
                        if ($note !== '') {
                            // Buat label item untuk kejelasan (nama + variasi)
                            $label = '';
                            if (!empty($it['nama_kostum'])) {
                                $label = $it['nama_kostum'] . (!empty($it['nama_variasi']) ? ' - ' . $it['nama_variasi'] : '');
                            } elseif (!empty($it['nama_layanan'])) {
                                $label = $it['nama_layanan'];
                            } else {
                                $label = 'Item #' . ($it['id'] ?? '');
                            }
                            $adminNotes[] = ['label' => $label, 'note' => $note];
                        }
                    }
                    ?>

                    <?php if (count($adminNotes) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach ($adminNotes as $an): ?>
                                <div class="p-4 border rounded-lg bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-accent"><?php echo htmlspecialchars($an['label']); ?></p>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($an['note'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600">Tidak ada catatan admin per item untuk pesanan ini.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right Column: Payment & Actions -->
        <div class="space-y-6">
            <!-- Payment Proof (dari tabel pembayaran) -->
            <?php if (!empty($payment['bukti_pembayaran'])): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-light-bg px-6 py-4 border-b border-beige-dark">
                        <h2 class="text-lg font-bold text-accent">
                            <i class="fas fa-receipt mr-2"></i>Bukti Pembayaran
                        </h2>
                    </div>
                    <div class="p-6">
                        <img src="<?php echo build_image_url('assets/images/payments/' . $payment['bukti_pembayaran']); ?>" 
                             alt="Bukti Pembayaran" 
                             class="w-full rounded-lg border-2 border-beige-dark cursor-pointer hover:opacity-80 transition"
                             onclick="viewImage(this.src)">
                        <p class="text-xs text-gray-500 mt-2 text-center">Klik untuk memperbesar</p>
                        <div class="mt-3 text-sm text-gray-700">
                            <p><strong>Jumlah Bayar:</strong> <?php echo formatRupiah($payment['jumlah_bayar']); ?></p>
                            <p><strong>Status Pembayaran:</strong> <?php echo htmlspecialchars($payment['status_pembayaran']); ?></p>
                            <p><strong>Tanggal Bayar:</strong> <?php echo !empty($payment['tanggal_bayar']) ? formatDate($payment['tanggal_bayar']) : '-'; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Jika pembayaran ditolak: tampilkan banner & alasan -->
            <?php
                $isPaymentRejected = false;
                if (!empty($payment['status_pembayaran']) && strtolower($payment['status_pembayaran']) === 'ditolak') {
                    $isPaymentRejected = true;
                }
                if (strtolower($transaction['status']) === 'ditolak') {
                    $isPaymentRejected = true;
                }
            ?>
            <?php if ($isPaymentRejected): ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <p class="font-medium text-red-700 mb-2">Pembayaran Anda ditolak.</p>
                            <?php
                                // prioritas alasan:
                                // 1. $payment['catatan_admin'] (jika status pembayaran = ditolak)
                                // 2. $transaction['catatan_admin']
                                // 3. fallback pesan umum
                            ?>
                            <?php if (!empty($payment['status_pembayaran']) && strtolower($payment['status_pembayaran']) === 'ditolak' && !empty($payment['catatan_admin'])): ?>
                                <p class="text-sm text-red-600"><?php echo nl2br(htmlspecialchars($payment['catatan_admin'])); ?></p>
                            <?php elseif (!empty($transaction['catatan_admin'])): ?>
                                <p class="text-sm text-red-600"><?php echo nl2br(htmlspecialchars($transaction['catatan_admin'])); ?></p>
                            <?php else: ?>
                                <p class="text-sm text-red-600">Silakan upload ulang bukti pembayaran dengan bukti yang jelas (notifikasi: lampiran blur atau tidak terbaca biasanya ditolak).</p>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
            <?php endif; ?>

            <!-- Bank Account Info (tampil bila status pending) -->
            <?php if ($transaction['status'] == 'pending'): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-light-bg px-6 py-4 border-b border-beige-dark">
                        <h2 class="text-lg font-bold text-accent">
                            <i class="fas fa-university mr-2"></i>Info Rekening
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded mb-4">
                            <p class="text-sm text-blue-800 mb-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Silakan transfer ke rekening berikut:
                            </p>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-600">Bank</p>
                                <p class="font-bold text-accent text-lg">BCA</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">No. Rekening</p>
                                <p class="font-bold text-accent text-lg font-mono">1234567890</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600">Atas Nama</p>
                                <p class="font-bold text-accent text-lg">Charitize</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Order Timeline (sederhana berdasarkan status) -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-light-bg px-6 py-4 border-b border-beige-dark">
                    <h2 class="text-lg font-bold text-accent">
                        <i class="fas fa-history mr-2"></i>Proses Pesanan
                    </h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <!-- Dibuat -->
                        <div class="flex gap-3">
                            <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="font-medium text-accent">Pesanan Dibuat</p>
                                <p class="text-xs text-gray-600"><?php echo date('d M Y, H:i', strtotime($transaction['tanggal_pemesanan'])); ?></p>
                            </div>
                        </div>

                        <!-- Pembayaran -->
                        <?php if ($payment && $payment['status_pembayaran'] == 'terkonfirmasi'): ?>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-accent">Pembayaran Diterima</p>
                                    <p class="text-xs text-gray-600"><?php echo !empty($payment['tanggal_bayar']) ? date('d M Y, H:i', strtotime($payment['tanggal_bayar'])) : '-'; ?></p>
                                </div>
                            </div>
                        <?php elseif ($isPaymentRejected): ?>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-red-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-ban text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-red-600">Pembayaran Ditolak</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-clock text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-500">Menunggu konfirmasi Pembayaran</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Diproses -->
                        <?php if ($transaction['status'] == 'proses' || $transaction['status'] == 'selesai'): ?>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-accent">Pesanan Diproses</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-clock text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-500">Pesanan Diproses</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Selesai -->
                        <?php if ($transaction['status'] == 'selesai'): ?>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-accent">Pesanan Selesai</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-clock text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-500">Pesanan Selesai</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Dibatalkan -->
                        <?php if ($transaction['status'] == 'batal'): ?>
                            <div class="flex gap-3">
                                <div class="w-8 h-8 rounded-full bg-red-500 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-times text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-red-600">Pesanan Dibatalkan</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="p-6 space-y-3">
                    <?php if ($transaction['status'] == 'pending'): ?>
                        <!-- Perbaikan: gunakan $transaction, bukan $order -->
                        <button onclick="showUploadPayment(<?php echo (int)$transaction['id']; ?>, <?php echo json_encode((float)$transaction['total_harga']); ?>)" 
                                class="w-full btn-primary-custom py-3 rounded-lg font-medium text-center">
                            <i class="fas fa-upload mr-2"></i>Upload Bukti Pembayaran
                        </button>
                    <?php elseif (strtolower($transaction['status']) == 'ditolak' || (!empty($payment['status_pembayaran']) && strtolower($payment['status_pembayaran']) === 'ditolak')): ?>
                        <!-- Tombol Upload Ulang ketika transaksi atau payment berstatus ditolak -->
                        <button onclick="showUploadPayment(<?php echo (int)$transaction['id']; ?>, <?php echo json_encode((float)$transaction['total_harga']); ?>)" 
                                class="w-full btn-primary-custom py-3 rounded-lg font-medium text-center">
                            <i class="fas fa-upload mr-2"></i>Upload Ulang Bukti Pembayaran
                        </button>
                    <?php elseif ($transaction['status'] == 'proses'): ?>
                        <button onclick="confirmOrderReceived(<?php echo $transaction['id']; ?>)" 
                                class="w-full btn-primary-custom py-3 rounded-lg font-medium text-center">
                            <i class="fas fa-check-circle mr-2"></i>Pesanan Diterima
                        </button>
                    <?php elseif ($transaction['status'] == 'selesai'): ?>
                        <a href="<?php echo BASE_URL; ?>customer/invoice.php?transaction_id=<?php echo $transaction['id']; ?>" 
                           target="_blank"
                           class="w-full btn-primary-custom py-3 rounded-lg font-medium text-center inline-block">
                            <i class="fas fa-file-pdf mr-2"></i>Cetak Invoice
                        </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo BASE_URL; ?>customer/orders.php" 
                       class="w-full btn-secondary-custom py-3 rounded-lg font-medium text-center inline-block">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali ke Pesanan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center p-4" onclick="closeImageModal()">
    <div class="relative max-w-4xl w-full">
        <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white text-4xl hover:text-gray-300">
            <i class="fas fa-times"></i>
        </button>
        <img id="modalImage" src="" alt="Bukti Pembayaran" class="w-full rounded-lg">
    </div>
</div>

<!-- Upload Payment Modal (tidak menampilkan metode/jumlah, dikirim default) -->
<div id="uploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-accent">Upload Bukti Pembayaran</h3>
            <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <!-- Form: hanya file terlihat. Metode & jumlah disertakan tapi sebagai hidden -->
        <form id="uploadPaymentForm" enctype="multipart/form-data">
            <!-- sesuai upload-payment.php -->
            <input type="hidden" id="upload_order_id" name="id_transaksi">
            <input type="hidden" id="metode_pembayaran" name="metode_pembayaran" value="Transfer">
            <input type="hidden" id="jumlah_bayar" name="jumlah_bayar" value="0">

            <div class="bg-light-bg rounded-lg p-4 mb-4">
                <h4 class="font-bold text-accent mb-2">Informasi Rekening</h4>
                <div class="space-y-1 text-sm">
                    <p><strong>Bank:</strong> BCA</p>
                    <p><strong>No. Rekening:</strong> 1234567890</p>
                    <p><strong>Atas Nama:</strong> Charitize</p>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-accent font-medium mb-2">
                    Upload Bukti Transfer <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="flex items-center gap-3">
                        <label for="bukti_pembayaran" class="btn-secondary-custom px-6 py-3 rounded-lg cursor-pointer inline-block">
                            <i class="fas fa-image mr-2"></i>Pilih File
                        </label>
                        <span id="payment_file_name" class="text-gray-600 text-sm">Tidak ada file dipilih</span>
                        <input type="file" 
                               name="bukti_pembayaran" 
                               id="bukti_pembayaran"
                               accept="image/*" 
                               required
                               onchange="previewImage(this, 'preview_payment'); updateFileName(this, 'payment_file_name')"
                               class="absolute opacity-0 -z-10">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG (Max 5MB)</p>
                </div>
            </div>

            <div class="mb-4">
                <img id="preview_payment" src="" alt="" class="hidden w-full rounded-lg border-2 border-beige-dark">
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeUploadModal()" 
                        class="flex-1 bg-gray-200 hover:bg-gray-300 py-2 rounded-lg font-medium transition">
                    Batal
                </button>
                <button type="submit" 
                        class="flex-1 btn-primary-custom py-2 rounded-lg font-medium">
                    <i class="fas fa-upload mr-2"></i>Upload
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function viewImage(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
}

function showUploadPayment(transaksiId, amount) {
    // set hidden fields
    document.getElementById('upload_order_id').value = transaksiId;
    document.getElementById('metode_pembayaran').value = 'Transfer';
    // pastikan amount numeric, jika null gunakan 0
    const jumlahField = document.getElementById('jumlah_bayar');
    jumlahField.value = (typeof amount !== 'undefined' && amount !== null && amount !== '') ? Number(amount) : 0;

    // Reset file preview & label
    document.getElementById('payment_file_name').textContent = 'Tidak ada file dipilih';
    const preview = document.getElementById('preview_payment');
    preview.src = '';
    preview.classList.add('hidden');
    document.getElementById('bukti_pembayaran').value = '';

    // show modal
    document.getElementById('uploadModal').classList.remove('hidden');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    const form = document.getElementById('uploadPaymentForm');
    if (form) form.reset();
    document.getElementById('preview_payment').classList.add('hidden');
    document.getElementById('payment_file_name').textContent = 'Tidak ada file dipilih';
}

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const file = input.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
        }
        reader.readAsDataURL(file);
    } else {
        preview.src = '';
        preview.classList.add('hidden');
    }
}

function updateFileName(input, spanId) {
    const fileName = input.files[0]?.name;
    document.getElementById(spanId).textContent = fileName || 'Tidak ada file dipilih';
}

document.getElementById('uploadPaymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    showLoading();
    
    fetch('<?php echo BASE_URL; ?>api/upload-payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            closeUploadModal();
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: data.message,
                confirmButtonColor: '#7A6A54'
            }).then(() => {
                location.reload();
            });
        } else {
            showError(data.message || 'Terjadi kesalahan saat mengupload');
        }
    })
    .catch(error => {
        hideLoading();
        showError('Terjadi kesalahan');
        console.error(error);
    });
});

function confirmOrderReceived(orderId) {
    Swal.fire({
        title: 'Konfirmasi Penerimaan',
        html: `
            <div class="text-left">
                <p class="mb-4">Apakah Anda yakin telah menerima pesanan ini?</p>
                <div class="bg-yellow-50 p-4 rounded-lg border-l-4 border-yellow-500">
                    <p class="text-sm text-yellow-700 mb-2">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Penting:</strong> Pastikan Anda telah:
                    </p>
                    <ul class="list-disc ml-8 mt-2 text-sm text-yellow-700">
                        <li>Menerima paket pesanan dengan baik</li>
                        <li>Mengecek kelengkapan barang pesanan</li>
                        <li>Memastikan kondisi barang dalam keadaan baik</li>
                    </ul>
                </div>
                <p class="mt-4 text-sm text-gray-600">
                    Setelah Anda mengkonfirmasi penerimaan, status pesanan akan berubah menjadi "selesai".
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check mr-2"></i>Ya, Pesanan Sudah Saya Terima',
        cancelButtonText: '<i class="fas fa-times mr-2"></i>Belum, Periksa Lagi',
        confirmButtonColor: '#7A6A54',
        cancelButtonColor: '#6B7280',
        reverseButtons: true,
        width: '600px'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Sedang mengupdate status pesanan',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('complete_order', '1');

            fetch('<?php echo BASE_URL; ?>api/complete-order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new TypeError("Response bukan JSON");
                }
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        confirmButtonColor: '#7A6A54'
                    }).then(() => {
                        window.location.href = '<?php echo BASE_URL; ?>customer/orders.php?status=selesai';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: data.message || 'Terjadi kesalahan saat memproses pesanan',
                        confirmButtonColor: '#7A6A54'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan saat memproses pesanan: ' + error.message,
                    confirmButtonColor: '#7A6A54'
                });
            });
        }
    });
}

function showLoading() {
    Swal.fire({
        title: 'Memproses...',
        text: 'Sedang mengupload bukti pembayaran',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: message,
        confirmButtonColor: '#7A6A54'
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
